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
        // Recarregar sempre os models do banco (blindado e seguro)
        $user = User::find($this->userId);
        $tx   = Transaction::find($this->txId);

        if (!$user || !$tx) {
            Log::warning("⚠️ Webhook Pix Create ignorado — User ou TX não encontrados", [
                'user_id' => $this->userId,
                'tx_id'   => $this->txId,
            ]);
            return;
        }

        // Verificação de webhook configurado
        if (!$user->webhook_enabled || !$user->webhook_in_url) {
            Log::info("ℹ️ Webhook IN desabilitado — ignorando envio (Pix Create)", [
                'user_id' => $user->id,
            ]);
            return;
        }

        try {

            $payload = [
                'type'            => 'Pix Create',
                'event'           => 'created',
                'transaction_id'  => $tx->id,
                'external_id'     => $tx->external_reference,
                'user'            => $user->name,
                'amount'          => (float) $tx->amount,
                'fee'             => (float) $tx->fee,
                'currency'        => $tx->currency,
                'status'          => $tx->status,
                'txid'            => $tx->txid,
                'e2e'             => $tx->e2e_id,
                'direction'       => $tx->direction,
                'method'          => $tx->method,

                // Datas ISO8601 – padrão global
                'created_at'      => optional($tx->created_at)->toISOString(),
                'updated_at'      => optional($tx->updated_at)->toISOString(),

                'provider_payload'=> $tx->provider_payload,
            ];

            $response = Http::timeout(10)->post($user->webhook_in_url, $payload);

            Log::info("✅ Webhook Pix Create enviado", [
                'user_id' => $user->id,
                'tx_id'   => $tx->id,
                'status'  => $response->status(),
            ]);

        } catch (\Throwable $e) {

            Log::warning("⚠️ Webhook Pix Create falhou", [
                'user_id' => $user->id,
                'tx_id'   => $tx->id,
                'error'   => $e->getMessage(),
            ]);

            throw $e; // permite retry automático do Laravel Queue
        }
    }
}
