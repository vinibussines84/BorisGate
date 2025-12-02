<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\TransactionStatus;
use App\Services\Lumnis\LumnisService;
use App\Jobs\SendWebhookPixCreatedJob;
use Carbon\Carbon;

class TransactionPixController extends Controller
{
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
            'email'       => ['sometimes', 'email', 'max:120'],
            'document'    => ['required', 'string', 'min:11', 'max:18'],
            'phone'       => ['sometimes', 'string', 'max:20'],
            'external_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $amountReais = (float) $data['amount'];
        $amountCents = (int) round($amountReais * 100);

        $externalId  = $data['external_id'];
        $name        = $data['name']     ?? $user->name     ?? 'Cliente';
        $email       = $data['email']    ?? $user->email    ?? 'no-email@placeholder.com';
        $phone       = $data['phone']    ?? $user->phone    ?? '11999999999';
        $document    = preg_replace('/\D/', '', $data['document']);

        // ❌ Duplicidade
        if (Transaction::where('user_id', $user->id)
            ->where('external_reference', $externalId)
            ->exists()) {
            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists for this user.",
            ], 409);
        }

        // 🧮 Criação local
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
            'provider_payload'   => compact('name', 'email', 'document'),
            'ip'                 => $request->ip(),
            'user_agent'         => $request->userAgent(),
        ]);

        // 📦 Payload oficial da Lumnis
        $payload = [
            'amount'      => $amountCents,
            'externalRef' => $externalId,
            'postback'    => route('webhooks.lumnis'),

            'customer' => [
                'name'     => $name,
                'email'    => $email,
                'phone'    => $phone,
                'document' => $document,
                'address'  => [
                    'street'  => $user->address_street  ?? 'N/A',
                    'number'  => $user->address_number  ?? '0',
                    'city'    => $user->address_city    ?? 'N/A',
                    'state'   => $user->address_state   ?? 'XX',
                    'country' => 'Brasil',
                    'zip'     => $user->address_zip     ?? '00000-000',
                ],
            ],

            'items' => [[
                'title'     => 'PIX',
                'unitPrice' => $amountCents,
                'quantity'  => 1,
                'tangible'  => false,
            ]],

            'method' => 'PIX',
        ];

        Log::info('LUMNIS_ENVIANDO_PAYLOAD', $payload);

        // 🚀 Envia para Lumnis
        try {
            $response = $lumnis->createTransaction($payload);

            if (!in_array($response['status'], [200, 201])) {
                Log::error('LUMNIS_ERRO_RESPOSTA', [
                    'status'  => $response['status'],
                    'body'    => $response['body'],
                    'payload' => $payload,
                ]);
                throw new \Exception(json_encode($response['body']));
            }

            $body = $response['body'];

            // 🔥 Aqui é a correção:
            $transactionId = data_get($body, 'id');
            $qrCodeText    = data_get($body, 'qrcode');

            if (!$transactionId || !$qrCodeText) {
                throw new \Exception("Invalid Lumnis response");
            }

        } catch (\Throwable $e) {

            Log::error('LUMNIS_PIX_CREATE_ERROR', [
                'error'    => $e->getMessage(),
                'response' => $response['body'] ?? null,
            ]);

            $tx->updateQuietly(['status' => TransactionStatus::FALHA]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to create PIX transaction.',
            ], 500);
        }

        // 🕒 Data BR
        $createdAtBr = Carbon::now('America/Sao_Paulo')->toDateTimeString();

        // 🧩 Atualiza local
        $cleanRaw = [
            'id'           => $transactionId,
            'qrcode'       => $qrCodeText,
            'total'        => $amountCents,
            'currency'     => 'BRL',
            'method'       => 'PIX',
            'status'       => $body['status'] ?? 'PENDING',
            'customer'     => $body['customer'] ?? [],
            'external_ref' => $externalId,
            'created_at'   => $createdAtBr,
        ];

        $tx->updateQuietly([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => [
                'name'         => $name,
                'email'        => $email,
                'document'     => $document,
                'qr_code_text' => $qrCodeText,
                'provider_raw' => $cleanRaw,
            ],
        ]);

        // 📡 Webhook de criação
        if ($user->webhook_enabled && $user->webhook_in_url) {
            SendWebhookPixCreatedJob::dispatch($user->id, $tx->id);
        }

        // 🟦 Retorno FINAL — o mesmo que você já usa
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

        $tx = Transaction::where('external_reference', $externalId)
            ->where('user_id', $user->id)
            ->first();

        if (!$tx) {
            return response()->json(['success' => false, 'error' => 'Transaction not found.'], 404);
        }

        $payload = is_array($tx->provider_payload)
            ? $tx->provider_payload
            : json_decode($tx->provider_payload ?? '{}', true);

        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $tx->id,
                'external_id'     => $tx->external_reference,
                'status'          => $tx->status,
                'amount'          => (float) $tx->amount,
                'fee'             => (float) $tx->fee,
                'txid'            => $tx->txid,
                'provider_payload'=> $payload,
                'created_at'      => $tx->created_at,
                'updated_at'      => $tx->updated_at,
            ],
        ]);
    }

    // Helpers
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
