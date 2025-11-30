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

    protected Transaction $transaction;

    /**
     * Cria uma nova instância do Job.
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;

        // Coloca na fila específica
        $this->onQueue('webhooks');
    }

    /**
     * Executa o Job.
     */
    public function handle(): void
    {
        try {
            // Sempre pega a transação mais atualizada (para garantir consistência)
            $tx = $this->transaction->fresh(['user']);

            if (!$tx) {
                Log::warning('⚠️ Job Pix Update ignorado — transação não encontrada.', [
                    'transaction_id' => $this->transaction->id ?? null,
                ]);
                return;
            }

            $user = $tx->user;

            if (!$user || !$user->webhook_enabled || !$user->webhook_in_url) {
                Log::info('ℹ️ Webhook de Pix ignorado (usuário sem webhook configurado).', [
                    'transaction_id' => $tx->id,
                ]);
                return;
            }

            // 🚫 Idempotência: se o status não for PAGA, ignora
            if (!$tx->isPaga()) {
                Log::info('ℹ️ Webhook de Pix ignorado (status não é pago).', [
                    'transaction_id' => $tx->id,
                    'status' => $tx->status,
                ]);
                return;
            }

            // Monta o payload
            $payload = [
                "type"             => "Pix Update",
                "event"            => "updated",
                "transaction_id"   => $tx->id,
                "external_id"      => $tx->external_reference,
                "user"             => $user->name,
                "amount"           => number_format($tx->amount, 2, '.', ''),
                "fee"              => number_format($tx->fee, 2, '.', ''),
                "currency"         => $tx->currency,
                "status"           => "paga",
                "txid"             => $tx->txid,
                "e2e"              => $tx->e2e_id,
                "direction"        => $tx->direction,
                "method"           => $tx->method,
                "created_at"       => $tx->created_at,
                "updated_at"       => $tx->updated_at,
                "paid_at"          => $tx->paid_at,
                "canceled_at"      => $tx->canceled_at,
                "provider_payload" => $tx->provider_payload,
            ];

            // Envia webhook
            $response = Http::timeout(10)->post($user->webhook_in_url, $payload);

            Log::info('✅ Webhook Pix Update enviado com sucesso', [
                'transaction_id' => $tx->id,
                'status'         => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook Pix Update', [
                'transaction_id' => $this->transaction->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
