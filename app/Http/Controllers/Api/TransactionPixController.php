<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Services\Lumnis\LumnisService;

class TransactionPixController extends Controller
{
    /**
     * 🧾 Create a new PIX transaction
     */
    public function store(Request $request, LumnisService $lumnis)
    {
        // 🔒 Required headers
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json([
                'success' => false,
                'error'   => 'Missing authentication headers.'
            ], 401);
        }

        // 🔑 Resolve user by keys
        $user = $this->resolveUser($auth, $secret);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid credentials.'
            ], 401);
        }

        // 🧩 Basic validation
        $data = $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'name'         => ['sometimes', 'string', 'max:100'],
            'email'        => ['sometimes', 'email', 'max:120'],
            'phone'        => ['sometimes', 'string', 'max:20'],
            'document'     => ['sometimes', 'string', 'max:20'],
            'external_id'  => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $amountReais = (float) $data['amount'];
        $amountCents = (int) round($amountReais * 100);
        $externalId  = $data['external_id'];

        // 🚫 Bloqueia duplicados
        $duplicate = Transaction::where('user_id', $user->id)
            ->where('external_reference', '=', $externalId)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists for this user."
            ], 409);
        }

        // 🔎 CPF validation
        $cpf = preg_replace('/\D/', '', ($data['document'] ?? $user->cpf_cnpj ?? ''));

        if (!$cpf || strlen($cpf) !== 11 || !$this->validateCpf($cpf)) {
            return response()->json([
                'success' => false,
                'field'   => 'document',
                'error'   => 'Invalid CPF. Provide a valid document.'
            ], 422);
        }

        // ☎️ Phone validation
        $phone = preg_replace('/\D/', '', ($data['phone'] ?? $user->phone ?? ''));

        if (!$phone || strlen($phone) < 11 || strlen($phone) > 12) {
            return response()->json([
                'success' => false,
                'field'   => 'phone',
                'error'   => 'Invalid phone number. Use DDD + number. Example: 11999999999'
            ], 422);
        }

        // 👤 Customer data
        $name  = $data['name']  ?? $user->name ?? $user->nome_completo ?? 'Client';
        $email = $data['email'] ?? $user->email ?? 'no-email@placeholder.com';

        try {
            $result = DB::transaction(function () use (
                $user, $request, $amountReais, $amountCents, $cpf, $name, $email, $phone, $externalId, $lumnis
            ) {
                // Cria transação local
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
                    'ip'         => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Payload para Lumnis
                $payload = [
                    "amount"      => $amountCents,
                    "externalRef" => $externalId,
                    "postback"    => route('webhooks.lumnis'),
                    "customer"    => [
                        "name"     => $name,
                        "email"    => $email,
                        "phone"    => $phone,
                        "document" => $cpf,
                    ],
                    "items" => [[
                        "title"     => "PIX Payment",
                        "unitPrice" => $amountCents,
                        "quantity"  => 1,
                        "tangible"  => false,
                    ]],
                    "method"       => "PIX",
                    "installments" => 1,
                ];

                // Envia à API da Lumnis
                $response = $lumnis->createTransaction($payload);

                if (!in_array($response["status"], [200, 201])) {
                    throw new \Exception("Lumnis error: " . json_encode($response["body"]));
                }

                $dataAPI = is_array($response["body"])
                    ? $response["body"]
                    : json_decode($response["body"], true);

                $transactionId = data_get($dataAPI, 'id');
                $qrCodeText    = data_get($dataAPI, 'qrcode');

                if (!$transactionId || !$qrCodeText) {
                    throw new \Exception("Invalid Lumnis response");
                }

                // Atualiza com dados do provedor
                $tx->update([
                    'txid'                    => $transactionId,
                    'provider_transaction_id' => $transactionId,
                    'provider_payload'        => json_encode([
                        'name'         => $name,
                        'email'        => $email,
                        'document'     => $cpf,
                        'phone'        => $phone,
                        'qr_code_text' => $qrCodeText,
                        'provider_raw' => $dataAPI,
                    ]),
                ]);

                // ✅ Envia webhook APENAS após a criação com sucesso (com QR code)
                if ($user->webhook_enabled && $user->webhook_in_url) {
                    try {
                        Http::timeout(10)->post($user->webhook_in_url, [
                            'type'            => 'Pix Create',
                            'event'           => 'created',
                            'transaction_id'  => $tx->id,
                            'external_id'     => $tx->external_reference,
                            'user'            => $user->name,
                            'amount'          => number_format($tx->amount, 2, '.', ''),
                            'fee'             => number_format($tx->fee, 2, '.', ''),
                            'currency'        => $tx->currency,
                            'status'          => $tx->status,
                            'txid'            => $tx->txid,
                            'e2e'             => $tx->e2e_id,
                            'direction'       => $tx->direction,
                            'method'          => $tx->method,
                            'created_at'      => $tx->created_at,
                            'updated_at'      => $tx->updated_at,
                            'paid_at'         => $tx->paid_at,
                            'canceled_at'     => $tx->canceled_at,
                            'provider_payload'=> $tx->provider_payload,
                        ]);
                    } catch (\Throwable $ex) {
                        \Log::warning('⚠️ Failed to send Pix webhook', [
                            'user_id' => $user->id,
                            'url'     => $user->webhook_in_url,
                            'error'   => $ex->getMessage(),
                        ]);
                    }
                }

                return [
                    'transaction_id' => $tx->id,
                    'external_id'    => $externalId,
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
            \Log::error("PIX_CREATION_ERROR", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Internal server error while creating PIX.'
            ], 500);
        }
    }

    public function status(Request $request, string $txid)
    {
        return $this->findTransaction($request, 'txid', $txid);
    }

    public function statusByExternal(Request $request, string $externalId)
    {
        return $this->findTransaction($request, 'external_reference', $externalId);
    }

    private function findTransaction(Request $request, string $field, string $value)
    {
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json(['success' => false, 'error' => 'Missing authentication headers.'], 401);
        }

        $user = $this->resolveUser($auth, $secret);

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Invalid credentials.'], 401);
        }

        $transaction = Transaction::where($field, $value)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'error' => 'Transaction not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'            => $transaction->id,
                'txid'          => $transaction->txid,
                'external_id'   => $transaction->external_reference,
                'amount'        => (float) $transaction->amount,
                'fee'           => (float) $transaction->fee,
                'status'        => $transaction->status,
                'created_at'    => $transaction->created_at,
                'updated_at'    => $transaction->updated_at,
            ],
        ]);
    }

    private function resolveUser(string $auth, string $secret)
    {
        return \App\Models\User::where('authkey', $auth)
            ->where('secretkey', $secret)
            ->first();
    }

    private function computeFee($user, float $amount): float
    {
        if (!($user->tax_in_enabled ?? false)) return 0.0;
        $fixed   = (float) ($user->tax_in_fixed ?? 0);
        $percent = (float) ($user->tax_in_percent ?? 0);
        return round(max(0, min($fixed + ($amount * $percent / 100), $amount)), 2);
    }

    private function validateCpf($cpf): bool
    {
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }
}
