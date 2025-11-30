<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Enums\TransactionStatus;
use App\Services\PodPay\PodPayService;
use App\Models\User;

class TransactionPixController extends Controller
{
    /**
     * 🧾 Create a new PIX transaction (CASH IN) — now using PodPay
     */
    public function store(Request $request, PodPayService $podpay)
    {
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json([
                'success' => false,
                'error'   => 'Missing authentication headers.'
            ], 401);
        }

        $user = $this->resolveUser($auth, $secret);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid credentials.'
            ], 401);
        }

        $data = $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'name'         => ['sometimes', 'string', 'max:100'],
            'email'        => ['sometimes', 'email', 'max:120'],
            'phone'        => ['sometimes', 'string', 'max:20'],
            'document'     => ['sometimes', 'string', 'max:20'],
            'external_id'  => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $amountReais  = (float) $data['amount'];
        $amountCents  = (int) round($amountReais * 100);
        $externalId   = $data['external_id'];

        if ($amountReais > 3000) {
            return response()->json([
                'success' => false,
                'error'   => 'The maximum allowed PIX amount is R$3000.'
            ], 422);
        }

        if (Transaction::where('user_id', $user->id)
            ->where('external_reference', '=', $externalId)
            ->exists()) {

            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists for this user."
            ], 409);
        }

        $cpf = preg_replace('/\D/', '', ($data['document'] ?? $user->cpf_cnpj ?? ''));

        if (!$cpf || strlen($cpf) !== 11 || !$this->validateCpf($cpf)) {
            return response()->json([
                'success' => false,
                'field'   => 'document',
                'error'   => 'Invalid CPF.'
            ], 422);
        }

        $phone = preg_replace('/\D/', '', ($data['phone'] ?? $user->phone ?? ''));

        if (!$phone || strlen($phone) < 11 || strlen($phone) > 12) {
            return response()->json([
                'success' => false,
                'field'   => 'phone',
                'error'   => 'Invalid phone number.'
            ], 422);
        }

        $name  = $data['name']  ?? $user->name ?? $user->nome_completo ?? 'Client';
        $email = $data['email'] ?? $user->email ?? 'no-email@placeholder.com';

        $tx = Transaction::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'direction'          => Transaction::DIR_IN,
            'status'             => TransactionStatus::PENDENTE,
            'currency'           => 'BRL',
            'method'             => 'pix',
            'provider'           => 'PodPay',
            'amount'             => $amountReais,
            'fee'                => $this->computeFee($user, $amountReais),
            'external_reference' => $externalId,
            'provider_payload'   => [
                'name'     => $name,
                'email'    => $email,
                'document' => $cpf,
                'phone'    => $phone,
            ],
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $payload = [
            "amount"        => $amountCents,
            "currency"      => "BRL",
            "paymentMethod" => "pix",
            "pix" => [
                "expiresInDays" => 1
            ],
            "customer" => [
                "name" => $name,
                "email" => $email,
                "phone" => $phone,
                "document" => [
                    "number" => $cpf,
                    "type"   => "cpf"
                ]
            ],
            "externalRef" => $externalId,
            "postbackUrl" => route('webhooks.podpay'),
            "items" => [[
                "title"      => "Pix Payment",
                "unitPrice"  => $amountCents,
                "quantity"   => 1,
                "tangible"   => false,
                "externalRef"=> "API-Pix"
            ]]
        ];

        try {
            $response = $podpay->createPixTransaction($payload);

            if (!in_array($response["status"], [200, 201])) {
                throw new \Exception(json_encode($response["body"]));
            }

            $body = $response["body"];

            $transactionId = data_get($body, 'id');
            $qrCodeText    = data_get($body, 'pix.qrcode');

            if (!$transactionId || !$qrCodeText) {
                throw new \Exception("Invalid PodPay response");
            }

        } catch (\Throwable $e) {

            Log::error("PODPAY_PIX_CREATE_ERROR", ['error' => $e->getMessage()]);

            $tx->update([
                'status' => TransactionStatus::FALHADO,
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to create PIX transaction.'
            ], 500);
        }

        $cleanRaw = [
            'id'          => data_get($body, 'id'),
            'total'       => data_get($body, 'amount'),
            'method'      => 'PIX',
            'qrcode'      => data_get($body, 'pix.qrcode'),
            'status'      => 'PENDING',
            'currency'    => data_get($body, 'currency'),
            'customer'    => [
                'name'     => data_get($body, 'customer.name'),
                'email'    => data_get($body, 'customer.email'),
                'phone'    => data_get($body, 'customer.phone'),
                'document' => data_get($body, 'customer.document.number'),
            ],
            'created_at'  => data_get($body, 'createdAt'),
            'identifier'  => data_get($body, 'pix.end2EndId'),
            'external_ref'=> data_get($body, 'externalRef'),
        ];

        $providerPayload = [
            'name'         => $name,
            'email'        => $email,
            'document'     => $cpf,
            'phone'        => $phone,
            'qr_code_text' => $qrCodeText,
            'provider_raw' => $cleanRaw,
        ];

        $tx->update([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => $providerPayload,
        ]);

        if ($user->webhook_enabled && $user->webhook_in_url) {

            dispatch(function () use ($user, $tx) {
                try {
                    Http::post($user->webhook_in_url, [
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
                } catch (\Throwable $e) {
                    Log::warning("⚠️ Failed webhook (async)", [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            })->onQueue('webhooks');
        }

        return response()->json([
            'success'        => true,
            'transaction_id' => $tx->id,
            'external_id'    => $externalId,
            'status'         => $tx->status,
            'amount'         => number_format($amountReais, 2, '.', ''),
            'fee'            => number_format($tx->fee, 2, '.', ''),
            'txid'           => $transactionId,
            'qr_code_text'   => $qrCodeText,
        ]);
    }

    /**
     * 🟦 CONSULTA POR EXTERNAL ID (CORRIGIDO)
     */
    public function statusByExternal(Request $request, string $externalId)
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

        /**
         * PIX-IN
         */
        $tx = Transaction::where('external_reference', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if ($tx) {
            $payload = is_array($tx->provider_payload)
                ? $tx->provider_payload
                : json_decode($tx->provider_payload ?? '{}', true);

            return response()->json([
                'success' => true,
                'type'    => 'pix_in',
                'data' => [
                    'id'             => $tx->id,
                    'external_id'    => $tx->external_reference,
                    'status'         => $tx->status,
                    'amount'         => (float) $tx->amount,
                    'fee'            => (float) $tx->fee,
                    'txid'           => $tx->txid,
                    'e2e'            => $tx->e2e_id,
                    'payer_name'     => data_get($payload, 'payer_name'),
                    'payer_document' => data_get($payload, 'payer_document'),
                    'identifier'     => data_get($payload, 'provider_raw.identifier'),
                    'provider_payload' => $payload,
                    'created_at'     => $tx->created_at,
                    'updated_at'     => $tx->updated_at,
                    'paid_at'        => $tx->paid_at,
                ]
            ]);
        }

        /**
         * PIX-OUT
         */
        $withdraw = Withdraw::where('external_id', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if ($withdraw) {
            $meta = is_array($withdraw->meta) ? $withdraw->meta : [];

            return response()->json([
                'success' => true,
                'type'    => 'pix_out',
                'data' => [
                    'id'             => $withdraw->id,
                    'external_id'    => $withdraw->external_id,
                    'status'         => $withdraw->status,
                    'amount'         => (float) $withdraw->amount,
                    'gross_amount'   => (float) $withdraw->gross_amount,
                    'fee_amount'     => (float) $withdraw->fee_amount,
                    'pix_key'        => $withdraw->pixkey,
                    'pix_key_type'   => $withdraw->pixkey_type,
                    'provider_ref'   => $withdraw->provider_reference,

                    //  🔥 CORRIGIDO — agora retorna o E2E certo
                    'endtoend'       => $meta['e2e'] ?? $meta['endtoend'] ?? null,

                    'identifier'     => $meta['identifier'] ?? null,
                    'receiver_name'  => $meta['receiver_name'] ?? null,
                    'receiver_bank'  => $meta['receiver_bank'] ?? null,
                    'receiver_ispb'  => $meta['receiver_bank_ispb'] ?? null,
                    'paid_at'        => $meta['paid_at'] ?? $withdraw->processed_at,
                    'provider_payload' => $this->cleanWithdrawPayload($meta['raw_provider_payload'] ?? null),
                    'created_at' => $withdraw->created_at,
                    'updated_at' => $withdraw->updated_at,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'error'   => 'No transaction or withdraw found for this external_id.',
        ], 404);
    }

    private function cleanWithdrawPayload($payload)
    {
        if (!is_array($payload)) return $payload;

        unset($payload['operation']['postback']);

        return $payload;
    }

    private function resolveUser(string $auth, string $secret)
    {
        return User::where('authkey', $auth)
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
            for ($d = 0, $c = 0; $c < $t; $c++)
                $d += $cpf[$c] * (($t + 1) - $c);
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }
}
