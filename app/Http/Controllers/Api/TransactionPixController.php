<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Models\User;
use App\Enums\TransactionStatus;
use App\Services\Lumnis\LumnisService;
use App\Jobs\SendWebhookPixCreatedJob;
use Carbon\Carbon;

class TransactionPixController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 🔥 Criar PIX (Cash-in)
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, LumnisService $lumnis)
    {
        // 🔐 Auth
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json(['success' => false, 'error' => 'Missing authentication headers.'], 401);
        }

        $user = $this->resolveUser($auth, $secret);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Invalid credentials.'], 401);
        }

        // 🧩 Validação
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'name'        => ['sometimes', 'string', 'max:100'],
            'document'    => ['required', 'string', 'min:11', 'max:18'],
            'phone'       => ['sometimes', 'string', 'max:20'],
            'external_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $amountReais = (float) $data['amount'];

        if ($amountReais > 4000) {
            return response()->json([
                'success' => false,
                'error'   => 'O valor máximo permitido para PIX é de R$ 4.000,00.',
            ], 422);
        }

        $amountCents = (int) round($amountReais * 100);
        $externalId  = $data['external_id'];
        $name        = $data['name'] ?? $user->name ?? 'Cliente';
        $document    = preg_replace('/\D/', '', $data['document']);
        $phone       = $data['phone'] ?? $user->phone ?? '11999999999';

        // ❌ Duplicidade
        if (Transaction::where('user_id', $user->id)
            ->where('external_reference', $externalId)
            ->exists()) {
            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists for this user.",
            ], 409);
        }

        // 🧮 Criar local
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
            'ip'                 => $request->ip(),
            'user_agent'         => $request->userAgent(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 🔧 Payload no formato LUMNIS — SOMENTE PIX
        |--------------------------------------------------------------------------
        */
        $payload = [
            "amount"      => $amountCents,
            "externalRef" => $externalId,
            "postback"    => $user->webhook_in_url ?? null,
            "method"      => "PIX",
            "installments"=> 1,
            "customer" => [
                "name"     => $name,
                "email"    => $user->email,
                "phone"    => $phone,
                "document" => $document,
                "address"  => [
                    "street"  => "N/D",
                    "number"  => "0",
                    "city"    => "N/D",
                    "state"   => "SP",
                    "country" => "Brasil",
                    "zip"     => "00000-000"
                ]
            ],
            "items" => [
                [
                    "title"     => "PIX Deposit",
                    "unitPrice" => $amountCents,
                    "quantity"  => 1,
                    "tangible"  => false
                ]
            ]
        ];

        // 🚀 Envia para Lumnis
        try {
            $response = $lumnis->createTransaction($payload);

            Log::info("LUMNIS_RAW_RESPONSE", $response);

            if (!in_array($response['status'], [200, 201])) {
                throw new \Exception("Provider error");
            }

            $body = $response['body'];

            // 🔍 CORREÇÃO BASEADA NO RETORNO REAL
            $transactionId = data_get($body, 'id');   
            $qrCodeText    = data_get($body, 'qrcode');

            if (!$transactionId || !$qrCodeText) {
                Log::error("LUMNIS_INVALID_RESPONSE", ['body' => $body]);
                throw new \Exception("Invalid provider response: missing id or qrcode");
            }

        } catch (\Throwable $e) {

            $tx->updateQuietly(['status' => TransactionStatus::FALHA]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to create PIX transaction.',
                'debug'   => $e->getMessage(),
            ], 500);
        }

        // Atualiza local
        $tx->updateQuietly([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => [
                'name'         => $name,
                'document'     => $document,
                'phone'        => $phone,
                'qr_code_text' => $qrCodeText,
                'provider_raw' => $body,
            ],
        ]);

        if ($user->webhook_enabled && $user->webhook_in_url) {
            SendWebhookPixCreatedJob::dispatch($user->id, $tx->id);
        }

        return response()->json([
            'success'        => true,
            'transaction_id' => $tx->id,
            'external_id'    => $externalId,
            'status'         => 'pendente',
            'amount'         => number_format($amountReais, 2, '.', ''),
            'fee'            => number_format($tx->fee, 2, '.', ''),
            'txid'           => $transactionId,
            'qr_code_text'   => $qrCodeText,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 Consultar (PIX + Saque)
    |--------------------------------------------------------------------------
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

        // 🔎 PIX
        $tx = Transaction::where('external_reference', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if ($tx) {
            return response()->json([
                'success' => true,
                'type'    => 'pix',
                'data' => [
                    'id'              => $tx->id,
                    'external_id'     => $tx->external_reference,
                    'status'          => $this->normalizeStatusPtBr($tx->status),
                    'amount'          => (float) $tx->amount,
                    'fee'             => (float) $tx->fee,
                    'txid'            => $tx->txid,
                    'provider_payload'=> $tx->provider_payload,
                    'created_at'      => $tx->created_at,
                    'updated_at'      => $tx->updated_at,
                ],
            ]);
        }

        // 🔎 Saque
        $withdraw = Withdraw::where('external_id', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if ($withdraw) {

            $meta    = $withdraw->meta ?? [];
            $receipt = data_get($meta, 'receipt', []);

            return response()->json([
                'success' => true,
                'type'    => 'withdraw',
                'event'   => 'withdraw.updated',
                'data' => [
                    'id'         => data_get($meta, 'internal_reference', $withdraw->id),
                    'status'     => $this->normalizeStatus($withdraw->status),
                    'E2E'        => data_get($meta, 'e2e'),
                    'requested'  => (float) $withdraw->gross_amount,
                    'paid'       => (float) $withdraw->amount,
                    'operation'  => [
                        'amount'      => (float) $withdraw->amount,
                        'key'         => $withdraw->pixkey,
                        'key_type'    => strtoupper($withdraw->pixkey_type),
                        'description' => 'Withdraw',
                        'details'     => data_get($meta, 'details', []),
                    ],
                    'receipt'     => $receipt,
                    'external_id' => $withdraw->external_id,
                ],
            ]);
        }

        return response()->json(['success' => false, 'error' => 'Transaction not found.'], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | utils
    |--------------------------------------------------------------------------
    */
    private function normalizeStatusPtBr(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'paga', 'approved', 'completed' => 'aprovado',
            'failed', 'erro', 'error', 'rejected', 'canceled', 'cancelled' => 'falhou',
            default => 'pendente',
        };
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'paid', 'paga', 'approved', 'completed' => 'APPROVED',
            'failed', 'erro', 'error', 'rejected', 'canceled', 'cancelled' => 'FAILED',
            default => 'PENDING',
        };
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
}
