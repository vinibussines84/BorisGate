<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Models\User;
use App\Enums\TransactionStatus;
use App\Services\Provider\ProviderService;
use App\Jobs\SendWebhookPixCreatedJob;
use App\Support\StatusMap;

class TransactionPixController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PIX CASH-IN (Pluggou via ProviderService)
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, ProviderService $provider)
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

        if ($amountReais > 10000) {
            return response()->json([
                'success' => false,
                'error'   => 'O valor máximo permitido para PIX é de R$ 10.000,00.',
            ], 422);
        }

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

        // 🧮 Criar registro local antes da requisição
        $tx = Transaction::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'direction'          => Transaction::DIR_IN,
            'status'             => TransactionStatus::PENDENTE,
            'currency'           => 'BRL',
            'method'             => 'pix',
            'provider'           => 'Pluggou',
            'amount'             => $amountReais,
            'fee'                => $this->computeFee($user, $amountReais),
            'external_reference' => $externalId,
            'ip'                 => $request->ip(),
            'user_agent'         => $request->userAgent(),
        ]);

        /*
        |--------------------------------------------------------------------------
        |  🚀 PLUGGOU - CRIA PIX (CORRIGIDO)
        |--------------------------------------------------------------------------
        */
        try {
            $response = $provider->createPix($amountReais, [
                "name"     => $name,
                "document" => $document,
                "phone"    => $phone,
            ]);

            Log::info("PLUGGOU_CREATE_PIX_RESPONSE", $response);

            // 🔥 PLUGGOU RETORNA DENTRO DE "data"
            $transactionId = data_get($response, "data.id");
            $qrCodeText    = data_get($response, "data.pix.emv");
            $qrCodeBase64  = data_get($response, "data.pix.qr_code_base64");

            if (!$transactionId || !$qrCodeText) {
                throw new \Exception("Invalid Pluggou response");
            }

        } catch (\Throwable $e) {

            $tx->updateQuietly(['status' => TransactionStatus::FALHA]);

            Log::error("PLUGGOU_CREATE_PIX_ERROR", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to create PIX transaction.',
            ], 500);
        }

        // 📌 Atualiza registro local
        $tx->updateQuietly([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => [
                'name'            => $name,
                'document'        => $document,
                'phone'           => $phone,
                'qr_code_text'    => $qrCodeText,
                'qr_code_base64'  => $qrCodeBase64,
            ],
        ]);

        // 🔔 Webhook assíncrono
        if ($user->webhook_enabled && $user->webhook_in_url) {
            SendWebhookPixCreatedJob::dispatch($user->id, $tx->id)
                ->onQueue('webhooks');
        }

        // ✅ Resposta final (compatível com padrão atual)
        return response()->json([
            'success'        => true,
            'transaction_id' => $tx->id,
            'external_id'    => $externalId,
            'status'         => StatusMap::normalize('pending'),
            'amount'         => number_format($amountReais, 2, '.', ''),
            'fee'            => number_format($tx->fee, 2, '.', ''),
            'txid'           => $transactionId,
            'qr_code_text'   => $qrCodeText,
            'qr_code_base64' => $qrCodeBase64,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS POR EXTERNAL_ID
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

        // 🔍 PIX TRANSACTION
        $tx = Transaction::where('external_reference', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if ($tx) {
            return response()->json([
                'type'            => 'Pix Create',
                'event'           => 'created',
                'transaction_id'  => $tx->id,
                'external_id'     => $tx->external_reference,
                'user'            => $user->name,
                'amount'          => (float) $tx->amount,
                'fee'             => (float) $tx->fee,
                'currency'        => $tx->currency,
                'status'          => StatusMap::normalize($tx->status),
                'txid'            => $tx->txid,
                'e2e'             => $tx->e2e_id,
                'direction'       => $tx->direction,
                'method'          => $tx->method,
                'created_at'      => optional($tx->created_at)->toISOString(),
                'updated_at'      => optional($tx->updated_at)->toISOString(),
                'provider_payload' => [
                    'name'            => $tx->provider_payload['name'] ?? null,
                    'phone'           => $tx->provider_payload['phone'] ?? null,
                    'document'        => $tx->provider_payload['document'] ?? null,
                    'qr_code_text'    => $tx->provider_payload['qr_code_text'] ?? null,
                    'qr_code_base64'  => $tx->provider_payload['qr_code_base64'] ?? null,
                ],
            ]);
        }

        // 🔍 WITHDRAW
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
                    'status'     => StatusMap::normalize($withdraw->status),
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
    | Utils
    |--------------------------------------------------------------------------
    */
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
