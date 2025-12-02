<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Enums\TransactionStatus;
use App\Services\Pluggou\PluggouService;
use App\Models\User;
use App\Jobs\SendWebhookPixCreatedJob;
use Carbon\Carbon;

class TransactionPixController extends Controller
{
    /**
     * 🧾 Criação de uma nova transação PIX (Cash In) — usando Pluggou
     */
    public function store(Request $request, PluggouService $pluggou)
    {
        // 🔐 Autenticação via headers
        $auth   = $request->header('X-Auth-Key');
        $secret = $request->header('X-Secret-Key');

        if (!$auth || !$secret) {
            return response()->json(['success' => false, 'error' => 'Missing authentication headers.'], 401);
        }

        $user = $this->resolveUser($auth, $secret);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Invalid credentials.'], 401);
        }

        // 🧩 Validação dos dados recebidos
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'name'        => ['sometimes', 'string', 'max:100'],
            'email'       => ['sometimes', 'email', 'max:120'],
            'external_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/'],
        ]);

        $amountReais = (float) $data['amount'];
        $amountCents = (int) round($amountReais * 100);
        $externalId  = $data['external_id'];

        // ❌ Evitar duplicidade
        if (Transaction::where('user_id', $user->id)
            ->where('external_reference', $externalId)
            ->exists()) {
            return response()->json([
                'success' => false,
                'error'   => "The external_id '{$externalId}' already exists for this user.",
            ], 409);
        }

        $name  = $data['name']  ?? $user->name ?? 'Cliente';
        $email = $data['email'] ?? $user->email ?? 'no-email@placeholder.com';

        // 🧮 Cria transação local
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
            'provider_payload'   => compact('name', 'email'),
            'ip'                 => $request->ip(),
            'user_agent'         => $request->userAgent(),
        ]);

        // 🔗 Payload Pluggou (sem postbackUrl)
        $payload = [
            'amount'        => $amountCents,
            'paymentMethod' => 'pix',
            'pix' => [
                'expiresInDays' => 1,
            ],
            'items' => [[
                'title'     => 'Pix',
                'unitPrice' => $amountCents,
                'quantity'  => 1,
                'tangible'  => false,
            ]],
            'customer' => [
                'name'  => $name,
                'email' => $email,
                'document' => [
                    'number' => '07814854016', // CPF fixo
                    'type'   => 'cpf',
                ],
            ],
            'externalRef' => $externalId,
        ];

        // 🚀 Envia requisição à API Pluggou
        try {
            $response = $pluggou->createTransaction($payload);

            if (!in_array($response['status'], [200, 201])) {
                throw new \Exception(json_encode($response['body']));
            }

            $body          = $response['body'];
            $transactionId = data_get($body, 'data.id');
            $qrCodeText    = data_get($body, 'data.pix.qrcode') ?? data_get($body, 'data.qrcode');

            if (!$transactionId || !$qrCodeText) {
                throw new \Exception('Invalid Pluggou response');
            }
        } catch (\Throwable $e) {
            Log::error('PLUGGOU_PIX_CREATE_ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $tx->updateQuietly(['status' => TransactionStatus::FALHA]);
            return response()->json(['success' => false, 'error' => 'Failed to create PIX transaction.'], 500);
        }

        // 🕒 Ajuste de timezone
        $createdAtBr = Carbon::now('America/Sao_Paulo')->toDateTimeString();

        // 🧩 Atualiza transação (sem observer)
        $cleanRaw = [
            'id'           => $transactionId,
            'total'        => data_get($body, 'data.amount'),
            'method'       => 'PIX',
            'qrcode'       => $qrCodeText,
            'status'       => data_get($body, 'data.status', 'PENDING'),
            'currency'     => 'BRL',
            'customer'     => $payload['customer'],
            'created_at'   => $createdAtBr,
            'external_ref' => $externalId,
        ];

        $tx->updateQuietly([
            'txid'                    => $transactionId,
            'provider_transaction_id' => $transactionId,
            'provider_payload'        => [
                'name'         => $name,
                'email'        => $email,
                'cpf'          => '07814854016',
                'qr_code_text' => $qrCodeText,
                'provider_raw' => $cleanRaw,
            ],
        ]);

        // 📡 Dispara webhook Pix Criado
        if ($user->webhook_enabled && $user->webhook_in_url) {
            SendWebhookPixCreatedJob::dispatch($user->id, $tx->id);
        }

        // 🎯 Retorno final
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
     * 🔍 Consulta via external_id
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

    // 🔧 Helpers
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
