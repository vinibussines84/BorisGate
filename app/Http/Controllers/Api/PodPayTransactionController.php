<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Services\PodPay\PodPayService;
use App\Models\User;

class PodPayTransactionController extends Controller
{
    public function store(Request $request, PodPayService $podpay)
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

        // 🔑 Resolve user
        $user = User::where('authkey', $auth)
                    ->where('secretkey', $secret)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid credentials.'
            ], 401);
        }

        // ✔ Validation
        $data = $request->validate([
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'name'         => ['sometimes', 'string', 'max:100'],
            'email'        => ['sometimes', 'email', 'max:120'],
            'phone'        => ['sometimes', 'string', 'max:20'],
            'document'     => ['sometimes', 'string', 'max:20'],
            'external_id'  => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $amountReais = (float) $data['amount'];

        // 🚫 LIMIT R$3000,00
        if ($amountReais > 3000) {
            return response()->json([
                'success' => false,
                'error'   => 'The maximum allowed PIX amount is R$3000. Please contact support.'
            ], 422);
        }

        $amountCents = (int) round($amountReais * 100);
        $externalId  = $data['external_id'];

        // ❌ Duplicate check
        if (Transaction::where('user_id', $user->id)
            ->where('external_reference', $externalId)
            ->exists()) {

            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists."
            ], 409);
        }

        // Customer data
        $cpf   = preg_replace('/\D/', '', $data['document'] ?? $user->cpf_cnpj ?? '');
        $phone = preg_replace('/\D/', '', $data['phone'] ?? $user->phone ?? '');
        $name  = $data['name']  ?? $user->name  ?? 'Client';
        $email = $data['email'] ?? $user->email ?? 'no-email@placeholder.com';

        // 1️⃣ Create local transaction
        $tx = Transaction::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'direction'          => Transaction::DIR_IN,
            'status'             => TransactionStatus::PENDENTE,
            'currency'           => 'BRL',
            'method'             => 'pix',
            'provider'           => 'PodPay',
            'amount'             => $amountReais,
            'fee'                => 0,
            'external_reference' => $externalId,
            'provider_payload'   => [],
            'ip'                 => $request->ip(),
            'user_agent'         => $request->userAgent(),
        ]);

        // 2️⃣ PodPay Payload
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
                "title"      => "Pix EquitPay",
                "unitPrice"  => $amountCents,
                "quantity"   => 1,
                "tangible"   => false,
                "externalRef"=> "Api Pix"
            ]]
        ];

        // 🔥 CALL PODPAY
        $response = $podpay->createPixTransaction($payload);

        if (!in_array($response["status"], [200, 201])) {
            $tx->update(['status' => TransactionStatus::FALHADO]);
            return response()->json([
                'success' => false,
                'error'   => 'PodPay provider error.',
                'details' => $response["body"]
            ], 500);
        }

        $body = $response["body"];
        $transactionId = data_get($body, 'id');
        $qrCodeText    = data_get($body, 'pix.qrcode');

        // 3️⃣ Update local
        $tx->update([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => $body,
        ]);

        // 4️⃣ CLEAN provider_raw EXACTLY LIKE LUMNIS
        $cleanRaw = [
            'id'          => data_get($body, 'id'),
            'total'       => data_get($body, 'amount'),
            'method'      => 'PIX',
            'qrcode'      => data_get($body, 'pix.qrcode'),
            'status'      => 'PENDING', // 🔥 FORÇADO — igual Lumnis
            'currency'    => data_get($body, 'currency', 'BRL'),
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

        // 5️⃣ Normalized Provider Payload
        $normalizedProviderPayload = [
            'name'         => data_get($body, 'customer.name'),
            'email'        => data_get($body, 'customer.email'),
            'phone'        => data_get($body, 'customer.phone'),
            'document'     => data_get($body, 'customer.document.number'),
            'qr_code_text' => $qrCodeText,
            'provider_raw' => $cleanRaw,
        ];

        // 6️⃣ Webhook — EXACT SAME FORMAT AS LUMNIS
        if ($user->webhook_enabled && $user->webhook_in_url) {

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
                    'e2e'             => null,
                    'direction'       => $tx->direction,
                    'method'          => $tx->method,
                    'created_at'      => $tx->created_at,
                    'updated_at'      => $tx->updated_at,
                    'paid_at'         => $tx->paid_at,
                    'canceled_at'     => $tx->canceled_at,
                    'provider_payload'=> $normalizedProviderPayload,
                ]);
            } catch (\Throwable $e) {
                Log::warning("⚠️ Failed PodPay webhook (sync)", [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 7️⃣ Response — identical pattern
        return response()->json([
            'success'        => true,
            'transaction_id' => $tx->id,
            'external_id'    => $externalId,
            'status'         => strtolower($tx->status),
            'amount'         => number_format($tx->amount, 2, '.', ''),
            'fee'            => number_format($tx->fee, 2, '.', ''),
            'txid'           => $transactionId,
            'qr_code_text'   => $qrCodeText,
        ]);
    }
}
