<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Services\Lumnis\LumnisService;

class TransactionPixController extends Controller
{
    private const MAX_PIX_REAIS = 5000;

    public function store(Request $request, LumnisService $lumnis)
    {
        // 🔒 Autenticação via headers
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json(['success' => false, 'error' => 'Headers ausentes'], 401);
        }

        $user = $this->resolveUser($auth, $secret);

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Credenciais inválidas'], 401);
        }

        // 🧩 Validação
        $data = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'name'     => ['sometimes', 'string', 'max:100'],
            'email'    => ['sometimes', 'email', 'max:120'],
            'phone'    => ['sometimes', 'string', 'max:20'],
            'document' => ['sometimes', 'string', 'max:20'],
        ]);

        $amountReais = (float) $data['amount'];
        $amountCents = (int) round($amountReais * 100);

        // CPF
        $cpf = preg_replace('/\D/', '', ($data['document'] ?? $user->cpf_cnpj ?? ''));

        if (!$cpf || strlen($cpf) < 11) {
            return response()->json(['success' => false, 'error' => 'CPF inválido ou ausente'], 422);
        }

        // Dados do cliente
        $name  = $data['name'] ?? $user->name ?? $user->nome_completo ?? 'Cliente';
        $email = $data['email'] ?? $user->email ?? 'sem-email@teste.com';

        // Lumnis exige 11 dígitos
        $phone = preg_replace('/\D/', '', ($data['phone'] ?? $user->phone ?? '11999999999'));
        if (strlen($phone) < 11) {
            $phone = "11999999999"; // fallback seguro
        }

        $externalId = $this->randomNumeric(18);

        try {
            $result = DB::transaction(function () use ($user, $request, $amountReais, $amountCents, $cpf, $name, $email, $phone, $externalId, $lumnis) {

                // Criação da transação local
                $tx = Transaction::create([
                    'tenant_id'          => $user->tenant_id,
                    'user_id'            => $user->id,
                    'direction'          => Transaction::DIR_IN,
                    'status'             => TransactionStatus::PENDENTE,
                    'currency'           => 'BRL',
                    'method'             => 'pix',
                    'provider'           => 'Lumnis',
                    'amount'             => $amountReais,
                    'fee'                => $this->computeFee($user, $amountReais),
                    'external_reference' => $externalId,
                    'provider_payload'   => json_encode([
                        'name'     => $name,
                        'email'    => $email,
                        'document' => $cpf,
                        'phone'    => $phone,
                    ]),
                    'ip'                 => $request->ip(),
                    'user_agent'         => $request->userAgent(),
                ]);

                // 🌐 Payload PARA a Lumnis (CÓDIGO CORRETO!)
                $payload = [
                    "amount"      => $amountCents,
                    "externalRef" => $externalId,
                    "postback"    => route('webhooks.lumnis'),
                    "customer"    => [
                        "name"     => $name,
                        "email"    => $email,
                        "phone"    => $phone,
                        "document" => $cpf,
                        "address"  => [
                            "street"  => "Rua Desconhecida",
                            "number"  => "S/N",
                            "city"    => "São Paulo",
                            "state"   => "SP",
                            "country" => "Brasil",
                            "zip"     => "00000-000",
                        ],
                    ],
                    "items" => [
                        [
                            "title"     => "Pagamento Pix",
                            "unitPrice" => $amountCents,
                            "quantity"  => 1,
                            "tangible"  => false,
                        ],
                    ],
                    "method"       => "PIX",
                    "installments" => 1,
                ];

                // LOG DO PAYLOAD FINAL PARA PRODUÇÃO
                \Log::info("LUMNIS_PAYLOAD_ENVIO", $payload);

                // Chamada API Lumnis
                $response = $lumnis->createTransaction($payload);

                if (!in_array($response["status"], [200, 201])) {
                    throw new \Exception("Erro Lumnis: " . json_encode($response["body"]));
                }

                $dataAPI = $response["body"];

                $transactionId = data_get($dataAPI, 'id');
                $qrCodeText    = data_get($dataAPI, 'qrcode');

                if (!$transactionId || !$qrCodeText) {
                    throw new \Exception("Retorno inválido da Lumnis");
                }

                // Atualizar transação
                DB::table('transactions')
                    ->where('id', $tx->id)
                    ->update([
                        'txid'                    => $transactionId,
                        'provider_transaction_id' => $transactionId,
                        'provider_payload'        => json_encode([
                            'name'     => $name,
                            'email'    => $email,
                            'document' => $cpf,
                            'phone'    => $phone,
                            'provider_response' => $dataAPI,
                            'qr_code_text'      => $qrCodeText,
                        ]),
                    ]);

                return [
                    'transaction_id' => $tx->id,
                    'status'         => $tx->status,
                    'amount'         => number_format($amountReais, 2, '.', ''),
                    'fee'            => number_format($tx->fee, 2, '.', ''),
                    'txid'           => $transactionId,
                    'qr_code_text'   => $qrCodeText,
                ];
            });

            return response()->json([
                'success' => true,
                ...$result
            ]);

        } catch (\Throwable $e) {

            // Logar erro real
            \Log::error("ERRO_PIX_PRODUCAO", [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'ERRO_PIX_500',
            ], 500);
        }
    }

    private function resolveUser(string $auth, string $secret)
    {
        return \App\Models\User::where('authkey', $auth)->where('secretkey', $secret)->first();
    }

    private function computeFee($user, float $amount): float
    {
        if (!($user->tax_in_enabled ?? false)) {
            return 0.0;
        }

        $fixed   = (float) ($user->tax_in_fixed ?? 0);
        $percent = (float) ($user->tax_in_percent ?? 0);

        return round(max(0, min($fixed + ($amount * $percent / 100), $amount)), 2);
    }

    private function randomNumeric(int $len): string
    {
        return collect(range(1, $len))->map(fn() => random_int(0, 9))->implode('');
    }
}
