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
    protected array $cleanPayload;

    /**
     * Cria uma nova instância do Job.
     */
    public function __construct(Transaction $transaction, array $cleanPayload = [])
    {
        $this->transaction = $transaction;
        $this->cleanPayload = $cleanPayload;

        // Coloca na fila específica
        $this->onQueue('webhooks');
    }

    /**
     * Executa o Job.
     */
    public function handle(): void
    {
        try {
            $user = $this->transaction->user;

            if (!$user || !$user->webhook_enabled || !$user->webhook_in_url) {
                Log::info('ℹ️ Webhook de Pix ignorado (usuário sem webhook configurado).', [
                    'transaction_id' => $this->transaction->id,
                ]);
                return;
            }

            $payload = [
                "type"            => "Pix Update",
                "event"           => "updated",
                "transaction_id"  => $this->transaction->id,
                "external_id"     => $this->transaction->external_reference,
                "user"            => $user->name,
                "amount"          => number_format($this->transaction->amount, 2, '.', ''),
                "fee"             => number_format($this->transaction->fee, 2, '.', ''),
                "currency"        => $this->transaction->currency,
                "status"          => "paga",
                "txid"            => $this->transaction->txid,
                "e2e"             => $this->transaction->e2e_id,
                "direction"       => $this->transaction->direction,
                "method"          => $this->transaction->method,
                "created_at"      => $this->transaction->created_at,
                "updated_at"      => $this->transaction->updated_at,
                "paid_at"         => $this->transaction->paid_at,
                "canceled_at"     => $this->transaction->canceled_at,
                "provider_payload"=> $this->cleanPayload,
            ];

            $response = Http::post($user->webhook_in_url, $payload);

            Log::info('✅ Webhook Pix Update enviado com sucesso', [
                'transaction_id' => $this->transaction->id,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook Pix Update', [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
