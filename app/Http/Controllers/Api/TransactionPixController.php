<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Models\User;
use App\Enums\TransactionStatus;
use App\Services\Pluggou\PluggouService;
use App\Jobs\SendWebhookPixCreatedJob;
use Carbon\Carbon;

class TransactionPixController extends Controller
{
    public function store(Request $request, PluggouService $pluggou)
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
        $amountCents = (int) round($amountReais * 100);

        $externalId = $data['external_id'];

        $name     = $data['name'] ?? $user->name ?? 'Cliente';
        $document = preg_replace('/\D/', '', $data['document']);
        $phone    = $data['phone'] ?? $user->phone ?? '11999999999';

        // ❌ Duplicidade
        if (Transaction::where('user_id', $user->id)
            ->where('external_reference', $externalId)
            ->exists()) {
            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists for this user.",
            ], 409);
        }

        // 🧮 Criação local — PENDENTE até webhook
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

        // 📦 Payload oficial da Pluggou
        $payload = [
            'payment_method' => 'pix',
            'amount'         => $amountCents,
            'buyer' => [
                'buyer_name'     => $name,
                'buyer_document' => $document,
                'buyer_phone'    => $phone,
            ],
        ];

        Log::info('PLUGGOU_ENVIANDO_PAYLOAD', $payload);

        // 🚀 Envia para Pluggou
        try {
            $response = $pluggou->createTransaction($payload);

            if (!in_array($response['status'], [200, 201])) {
                Log::error('PLUGGOU_ERRO_RESPOSTA', [
                    'status' => $response['status'],
                    'body'   => $response['body'],
                    'payload'=> $payload,
                ]);
                throw new \Exception(json_encode($response['body']));
            }

            $body = $response['body'];

            // 🔥 Pluggou retorna EMV dentro de: data.pix.emv
            $transactionId = data_get($body, 'data.id');
            $qrCodeText    = data_get($body, 'data.pix.emv');

            if (!$transactionId || !$qrCodeText) {
                Log::error('PLUGGOU_INVALID_RESPONSE', ['body' => $body]);
                throw new \Exception("Invalid Pluggou response: Missing id or EMV qrcode");
            }

        } catch (\Throwable $e) {

            Log::error('PLUGGOU_PIX_CREATE_ERROR', [
                'error'    => $e->getMessage(),
                'response' => $response['raw'] ?? null,
            ]);

            $tx->updateQuietly(['status' => TransactionStatus::FALHA]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to create PIX transaction.',
            ], 500);
        }

        // 🕒 Data BR
        $createdAtBr = Carbon::now('America/Sao_Paulo')->toDateTimeString();

        // 🧩 Atualiza local com dados da Pluggou
        $cleanRaw = [
            'id'           => $transactionId,
            'amount'       => $amountCents,
            'emv'          => $qrCodeText,
            'status'       => 'pending',
            'external_id'  => $externalId,
            'created_at'   => $createdAtBr,
        ];

        $tx->updateQuietly([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => [
                'name'         => $name,
                'document'     => $document,
                'phone'        => $phone,
                'qr_code_text' => $qrCodeText,
                'provider_raw' => $cleanRaw,
            ],
        ]);

        // 📡 Webhook de criação (interno)
        if ($user->webhook_enabled && $user->webhook_in_url) {
            SendWebhookPixCreatedJob::dispatch($user->id, $tx->id);
        }

        // 🟦 Retorno final — IGUAL AO MODELO ANTIGO
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

    /*
    |--------------------------------------------------------------------------
    | 🔥 NOVO STATUS UNIFICADO (Transaction + Withdraw)
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

        /*
        |--------------------------------------------------------------------------
        | 1) Primeiro tenta achar PIX (cash-in)
        |--------------------------------------------------------------------------
        */
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
                    'status'          => $tx->status,
                    'amount'          => (float) $tx->amount,
                    'fee'             => (float) $tx->fee,
                    'txid'            => $tx->txid,
                    'provider_payload'=> $tx->provider_payload,
                    'created_at'      => $tx->created_at,
                    'updated_at'      => $tx->updated_at,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Se não for PIX, tenta achar WITHDRAW (cash-out)
        |--------------------------------------------------------------------------
        */
        $withdraw = Withdraw::where('external_id', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if ($withdraw) {

            $meta = $withdraw->meta ?? [];
            $receipt = data_get($meta, 'receipt', []);

            return response()->json([
                'success' => true,
                'type'    => 'withdraw',
                'event'   => 'withdraw.updated',
                'data' => [
                    'id'         => data_get($meta, 'internal_reference', $withdraw->id),
                    'status'     => strtoupper($withdraw->status),
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

        /*
        |--------------------------------------------------------------------------
        | 3) Não achou nada
        |--------------------------------------------------------------------------
        */
        return response()->json(['success' => false, 'error' => 'Transaction not found.'], 404);
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
