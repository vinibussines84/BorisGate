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

    /**
     * Create a new PIX transaction
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
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'name'     => ['sometimes', 'string', 'max:100'],
            'email'    => ['sometimes', 'email', 'max:120'],
            'phone'    => ['sometimes', 'string', 'max:20'],
            'document' => ['sometimes', 'string', 'max:20'],
        ]);

        $amountReais = (float) $data['amount'];
        $amountCents = (int) round($amountReais * 100);

        // ======================
        // 📌 CPF validation
        // ======================
        $cpf = preg_replace('/\D/', '', ($data['document'] ?? $user->cpf_cnpj ?? ''));

        if (!$cpf || strlen($cpf) !== 11 || !$this->validateCpf($cpf)) {
            return response()->json([
                'success' => false,
                'field'   => 'document',
                'error'   => 'Invalid CPF. Provide a valid document.'
            ], 422);
        }

        // =======================
        // 📌 Phone validation
        // =======================
        $phone = preg_replace('/\D/', '', ($data['phone'] ?? $user->phone ?? ''));

        if (!$phone || strlen($phone) < 11 || strlen($phone) > 12) {
            return response()->json([
                'success' => false,
                'field'   => 'phone',
                'error'   => 'Invalid phone number. Use DDD + number. Example: 11999999999'
            ], 422);
        }

        // =======================
        // Customer data
        // =======================
        $name  = $data['name']  ?? $user->name ?? $user->nome_completo ?? 'Client';
        $email = $data['email'] ?? $user->email ?? 'no-email@placeholder.com';

        // =======================
        // Unique identifier
        // =======================
        $externalId = $this->randomNumeric(18);

        try {
            $result = DB::transaction(function () use (
                $user, $request, $amountReais, $amountCents, $cpf, $name, $email, $phone, $externalId, $lumnis
            ) {

                // 📌 Create local transaction
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

                // ======================
                // 📦 Payload for Lumnis
                // ======================
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
                            "street"  => "Unknown Street",
                            "number"  => "N/A",
                            "city"    => "São Paulo",
                            "state"   => "SP",
                            "country" => "Brazil",
                            "zip"     => "00000-000",
                        ],
                    ],
                    "items" => [
                        [
                            "title"     => "PIX Payment",
                            "unitPrice" => $amountCents,
                            "quantity"  => 1,
                            "tangible"  => false,
                        ],
                    ],
                    "method"       => "PIX",
                    "installments" => 1,
                ];

                \Log::info("LUMNIS_PAYLOAD_SENT", $payload);

                // ======================================
                // 🌍 Send to Lumnis API
                // ======================================
                $response = $lumnis->createTransaction($payload);

                if (!in_array($response["status"], [200, 201])) {
                    throw new \Exception("Lumnis error: " . json_encode($response["body"]));
                }

                $dataAPI = $response["body"];
                $transactionId = data_get($dataAPI, 'id');
                $qrCodeText    = data_get($dataAPI, 'qrcode');

                if (!$transactionId || !$qrCodeText) {
                    throw new \Exception("Invalid Lumnis response");
                }

                // 📌 Update local record
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
            \Log::error("PIX_CREATION_ERROR", [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Internal server error while creating PIX.'
            ], 500);
        }
    }

    /**
     * 🔍 Check transaction status by TXID
     * GET /api/v1/transaction/status/{txid}
     */
    public function status(Request $request, string $txid)
    {
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json([
                'success' => false,
                'error'   => 'Missing authentication headers.'
            ], 401);
        }

        // Find user
        $user = $this->resolveUser($auth, $secret);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid credentials.'
            ], 401);
        }

        // Find transaction
        $transaction = Transaction::where('txid', $txid)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'error'   => 'Transaction not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $transaction->id,
                'txid'            => $transaction->txid,
                'status'          => $transaction->status,
                'amount'          => $transaction->amount,
                'fee'             => $transaction->fee,
                'created_at'      => $transaction->created_at,
                'updated_at'      => $transaction->updated_at,
                'provider'        => $transaction->provider,
                'provider_payload'=> json_decode($transaction->provider_payload, true),
            ]
        ]);
    }

    // =================================================
    // 🔍 Resolve User
    // =================================================
    private function resolveUser(string $auth, string $secret)
    {
        return \App\Models\User::where('authkey', $auth)
                               ->where('secretkey', $secret)
                               ->first();
    }

    // =================================================
    // 💸 Compute Fee
    // =================================================
    private function computeFee($user, float $amount): float
    {
        if (!($user->tax_in_enabled ?? false)) {
            return 0.0;
        }

        $fixed   = (float) ($user->tax_in_fixed ?? 0);
        $percent = (float) ($user->tax_in_percent ?? 0);

        return round(
            max(0, min($fixed + ($amount * $percent / 100), $amount)),
            2
        );
    }

    // =================================================
    // 🔢 Random numeric generator
    // =================================================
    private function randomNumeric(int $len): string
    {
        return collect(range(1, $len))
            ->map(fn() => random_int(0, 9))
            ->implode('');
    }

    // =================================================
    // 🧠 CPF validator
    // =================================================
    private function validateCpf($cpf): bool
    {
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}
