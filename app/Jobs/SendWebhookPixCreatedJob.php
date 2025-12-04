<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Support\StatusMap;

class SendWebhookPixCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected int $txId;

    public function __construct(int $userId, int $txId)
    {
        $this->userId = $userId;
        $this->txId   = $txId;

        $this->onQueue('webhooks');
    }

    public function handle()
    {
        $user = User::find($this->userId);
        $tx   = Transaction::find($this->txId);

        if (!$user || !$tx) {
            Log::warning("⚠️ Webhook Pix Create ignorado — User ou TX não encontrados", [
                'user_id' => $this->userId,
                'tx_id'   => $this->txId,
            ]);
            return;
        }

        if (!$user->webhook_enabled || !$user->webhook_in_url) {
            Log::info("ℹ️ Webhook IN desabilitado — ignorando envio (Pix Create)", [
                'user_id' => $user->id,
            ]);
            return;
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | PAYLOAD LIMPO E PADRONIZADO
            |--------------------------------------------------------------------------
            */
            $payload = [
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

                // Somente o essencial do provider
                'provider_payload' => [
                    'name'         => $tx->provider_payload['name'] ?? null,
                    'phone'        => $tx->provider_payload['phone'] ?? null,
                    'document'     => $tx->provider_payload['document'] ?? null,
                    'qr_code_text' => $tx->provider_payload['qr_code_text'] ?? null,
                ],
            ];

            $response = Http::timeout(10)->post($user->webhook_in_url, $payload);

            Log::info("✅ Webhook Pix Create enviado", [
                'user_id' => $user->id,
                'tx_id'   => $tx->id,
                'status'  => $response->status(),
            ]);

        } catch (\Throwable $e) {

            Log::warning("⚠️ Falha ao enviar Webhook Pix Create (retry automático)", [
                'user_id' => $user->id,
                'tx_id'   => $tx->id,
                'error'   => $e->getMessage(),
            ]);

            throw $e; // habilita retry automático
        }
    }
}
