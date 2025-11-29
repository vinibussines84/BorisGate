<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Services\Pluggou\PluggouService;

class TransactionPixController extends Controller
{
    private const MAX_PIX_REAIS = 5000;

    public function store(Request $request, PluggouService $pluggou)
    {
        // ---------------------------
        // AUTH
        // ---------------------------
        $auth = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json(['success' => false, 'error' => 'Headers ausentes'], 401);
        }

        $user = $this->resolveUser($auth, $secret);

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Credenciais inválidas'], 401);
        }

        // ----------------------------------------------
        // VALIDAÇÃO
        // ----------------------------------------------
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'buyer_name'  => ['sometimes', 'string', 'max:100'],
            'buyer_phone' => ['sometimes', 'string', 'max:20'],
        ]);

        $amountReais = (float) $data['amount'];
        $amountCents = (int) round($amountReais * 100);

        // ----------------------------------------------
        // Regra especial — acima de R$ 1000
        // ----------------------------------------------
        $fee = null;
        if ($amountReais > 1000) {
            $amountReais = 985.45;
            $amountCents = (int) round(985.45 * 100);
            $fee = 0.0;
        }

        // CPF do usuário
        $cpfUser = preg_replace('/\D/', '', ($user->cpf_cnpj ?? ''));

        if (!$cpfUser || strlen($cpfUser) < 11) {
            return response()->json([
                'success' => false,
                'error'   => 'CPF do usuário inválido ou ausente',
            ], 422);
        }

        // Dados do comprador
        $buyer = [
            'buyer_name'    => $data['buyer_name'] ?? $user->nome_completo ?? $user->name,
            'buyer_document'=> $cpfUser,
            'buyer_phone'   => preg_replace('/\D/', '', ($data['buyer_phone'] ?? $user->phone ?? '11932698305')),
        ];

        // ID único
        $externalId = $this->randomNumeric(18);

        try {
            DB::beginTransaction();

            if ($fee === null) {
                $fee = $this->computeFee($user, $amountReais);
            }

            // -------------------------------------------------
            // REGISTRA NO BANCO
            // -------------------------------------------------
            $tx = Transaction::create([
                'tenant_id'             => $user->tenant_id,
                'user_id'               => $user->id,
                'direction'             => Transaction::DIR_IN,
                'status'                => TransactionStatus::PENDENTE,
                'currency'              => 'BRL',
                'method'                => 'pix',
                'provider'              => 'Pluggou',
                'amount'                => $amountReais,
                'fee'                   => $fee,
                'external_reference'    => $externalId,
                'provider_payload'      => [
                    'buyer' => $buyer,
                ],
                'ip'                    => $request->ip(),
                'user_agent'            => $request->userAgent(),
            ]);

            // -------------------------------------------------
            // PAYLOAD CORRETO PARA A PLUGGOU
            // -------------------------------------------------
            $payload = [
                "payment_method" => "pix",
                "amount"         => $amountCents,
                "buyer" => [
                    "buyer_name"    => $buyer['buyer_name'],
                    "buyer_document"=> $buyer['buyer_document'],
                    "buyer_phone"   => $buyer['buyer_phone'],
                ],
            ];

            // -------------------------------------------------
            // CHAMADA PLUGGOU
            // -------------------------------------------------
            $response = $pluggou->createTransaction($payload);

            if (!in_array($response["status"], [200, 201]) || empty($response["body"]["success"])) {
                Log::error("Pluggou ERROR RAW", [
                    "status"  => $response["status"],
                    "body"    => $response["body"],
                    "raw"     => $response["raw"],
                    "payload" => $payload,
                ]);

                throw new \Exception("Erro Pluggou: " . ($response["body"]["message"] ?? 'Indefinido'));
            }

            $dataAPI = $response["body"];

            $transactionId = data_get($dataAPI, 'data.id');
            $qrCodeText    = data_get($dataAPI, 'data.pix.emv');

            if (!$transactionId || !$qrCodeText) {
                throw new \Exception('Retorno inválido da Pluggou');
            }

            // -------------------------------------------------
            // ATUALIZA BANCO
            // -------------------------------------------------
            $tx->update([
                'txid'                   => $transactionId,
                'provider_transaction_id'=> $transactionId,
                'provider_payload'       => array_merge($tx->provider_payload, [
                    'provider_response' => $dataAPI,
                    'qr_code_text'      => $qrCodeText,
                ]),
            ]);

            DB::commit();

            // -------------------------------------------------
            // 🔥 RETORNO PADRÃO — NÃO ALTERADO 🔥
            // -------------------------------------------------
            return response()->json([
                'success'        => true,
                'transaction_id' => $tx->id,
                'status'         => $tx->status,
                'amount'         => number_format($amountReais, 2, '.', ''),
                'fee'            => number_format($fee, 2, '.', ''),
                'txid'           => $transactionId,
                'qr_code_text'   => $qrCodeText,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erro ao criar transação Pix (Pluggou)', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'ERRO_PIX_PLUGGOU_500',
            ], 500);
        }
    }

    // =====================================================
    // HELPERS
    // =====================================================

    private function resolveUser(string $auth, string $secret)
    {
        return \App\Models\User::where('authkey', $auth)
            ->where('secretkey', $secret)
            ->first();
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
        return collect(range(1, $len))
            ->map(fn () => random_int(0, 9))
            ->implode('');
    }
}
