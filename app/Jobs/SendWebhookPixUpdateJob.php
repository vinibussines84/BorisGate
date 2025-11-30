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

    protected int $transactionId;

    /**
     * Cria uma nova instância do Job.
     */
    public function __construct(int $transactionId)
    {
        $this->transactionId = $transactionId;

        // Coloca na fila específica
        $this->onQueue('webhooks');
    }

    /**
     * Executa o Job.
     */
    public function handle(): void
    {
        try {
            $transaction = Transaction::with('user')->find($this->transactionId);

            if (!$transaction) {
                Log::warning('⚠️ Job Pix Update ignorado — transação não encontrada.', [
                    'transaction_id' => $this->transactionId,
                ]);
                return;
            }

            $user = $transaction->user;

            if (!$user || !$user->webhook_enabled || !$user->webhook_in_url) {
                Log::info('ℹ️ Webhook de Pix ignorado (usuário sem webhook configurado).', [
                    'transaction_id' => $transaction->id,
                ]);
                return;
            }

            $payload = [
                "type"            => "Pix Update",
                "event"           => "updated",
                "transaction_id"  => $transaction->id,
                "external_id"     => $transaction->external_reference,
                "user"            => $user->name,
                "amount"          => number_format($transaction->amount, 2, '.', ''),
                "fee"             => number_format($transaction->fee, 2, '.', ''),
                "currency"        => $transaction->currency,
                "status"          => "paga",
                "txid"            => $transaction->txid,
                "e2e"             => $transaction->e2e_id,
                "direction"       => $transaction->direction,
                "method"          => $transaction->method,
                "created_at"      => $transaction->created_at,
                "updated_at"      => $transaction->updated_at,
                "paid_at"         => $transaction->paid_at,
                "canceled_at"     => $transaction->canceled_at,
                "provider_payload"=> $transaction->provider_payload,
            ];

            $response = Http::post($user->webhook_in_url, $payload);

            Log::info('✅ Webhook Pix Update enviado com sucesso', [
                'transaction_id' => $transaction->id,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook Pix Update', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
