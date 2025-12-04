<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookPixUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $txId;

    /**
     * Job recebe apenas o ID (seguro p/ serialização)
     */
    public function __construct(int $txId)
    {
        $this->txId = $txId;
        $this->onQueue('webhooks');
    }

    /**
     * Executa o Job.
     */
    public function handle(): void
    {
        try {
            // Sempre pega a versão atual da transação
            $tx = Transaction::with('user')->find($this->txId);

            if (!$tx || !$tx->user) {
                Log::warning('⚠️ Job Pix Update ignorado — TX ou User não encontrado.', [
                    'transaction_id' => $this->txId,
                ]);
                return;
            }

            $u = $tx->user;

            // Se o usuário não tem webhook ativo → ignora
            if (!$u->webhook_enabled || !$u->webhook_in_url) {
                Log::info('ℹ️ Webhook Pix Update ignorado — usuário sem webhook configurado.', [
                    'transaction_id' => $tx->id,
                ]);
                return;
            }

            // Webhook só é enviado quando transação está PAID
            if ($tx->status !== 'PAID') {
                Log::info('ℹ️ Webhook Pix Update ignorado — status não é PAID.', [
                    'transaction_id' => $tx->id,
                    'status'         => $tx->status,
                ]);
                return;
            }

            /**
             * ---------------------------------------------------------
             * MONTAGEM DO PAYLOAD FINAL (100% LIMPO)
             * ---------------------------------------------------------
             */
            $payload = [
                "type"           => "Pix Update",
                "event"          => "updated",
                "transaction_id" => $tx->id,
                "external_id"    => $tx->external_reference,
                "user"           => $u->name,
                "amount"         => number_format($tx->amount, 2, '.', ''),
                "fee"            => number_format($tx->fee, 2, '.', ''),
                "currency"       => $tx->currency,
                "status"         => "PAID",
                "txid"           => $tx->txid,
                "e2e"            => $tx->e2e_id,
                "direction"      => $tx->direction,
                "method"         => $tx->method,
                "created_at"     => optional($tx->created_at)->toISOString(),
                "updated_at"     => optional($tx->updated_at)->toISOString(),
                "paid_at"        => optional($tx->paid_at)->toISOString(),
                "canceled_at"    => optional($tx->canceled_at)->toISOString(),
            ];

            // Enviar webhook
            $response = Http::timeout(10)->post($u->webhook_in_url, $payload);

            Log::info('✅ Webhook Pix Update enviado com sucesso', [
                'transaction_id' => $tx->id,
                'status'         => $response->status(),
                'response'       => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook Pix Update', [
                'transaction_id' => $this->txId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
