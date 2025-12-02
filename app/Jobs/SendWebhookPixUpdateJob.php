<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhookPixUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $txId;

    public function __construct(int $txId)
    {
        $this->txId = $txId;
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        // 🔄 Recarregar SEMPRE a transação do banco
        $tx = Transaction::with('user')->find($this->txId);

        if (!$tx || !$tx->user) {
            Log::warning("⚠️ Webhook Pix Update ignorado — TX ou User não encontrados", [
                'tx_id' => $this->txId
            ]);
            return;
        }

        // Webhook desabilitado
        if (!$tx->user->webhook_enabled || !$tx->user->webhook_in_url) {
            Log::info("ℹ️ Usuário não tem webhook IN ativo", [
                'user_id' => $tx->user_id,
                'tx_id'   => $tx->id,
            ]);
            return;
        }

        try {

            $payload = [
                "type"            => "Pix Update",
                "event"           => "updated",
                "transaction_id"  => $tx->id,
                "external_id"     => $tx->external_reference,
                "user"            => $tx->user->name,
                "amount"          => $tx->amount,
                "fee"             => $tx->fee,
                "currency"        => $tx->currency,
                "status"          => $tx->status,
                "txid"            => $tx->txid,
                "e2e"             => $tx->e2e_id,
                "direction"       => $tx->direction,
                "method"          => $tx->method,
                "created_at"      => optional($tx->created_at)->toISOString(),
                "updated_at"      => optional($tx->updated_at)->toISOString(),
                "paid_at"         => optional($tx->paid_at)->toISOString(),
                "provider_payload"=> $tx->provider_payload
            ];

            $response = Http::timeout(10)->post(
                $tx->user->webhook_in_url,
                $payload
            );

            Log::info("✅ Webhook Pix Update enviado", [
                'tx_id'    => $tx->id,
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

        } catch (\Throwable $e) {

            Log::warning("⚠️ Falha ao enviar webhook Pix Update", [
                'transaction_id' => $tx->id,
                'error'          => $e->getMessage(),
            ]);

            throw $e; // requeue
        }
    }
}
