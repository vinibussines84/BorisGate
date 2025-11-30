<?php

namespace App\Jobs;

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

    public $user;
    public $tx;

    public function __construct($user, $tx)
    {
        $this->user = $user;
        $this->tx   = $tx;

        $this->onQueue('webhooks'); // Mesma fila que você usa hoje
    }

    public function handle()
    {
        try {
            $payload = [
                'type'            => 'Pix Create',
                'event'           => 'created',
                'transaction_id'  => $this->tx->id,
                'external_id'     => $this->tx->external_reference,
                'user'            => $this->user->name,
                'amount'          => $this->tx->amount,
                'fee'             => $this->tx->fee,
                'currency'        => $this->tx->currency,
                'status'          => $this->tx->status,
                'txid'            => $this->tx->txid,
                'e2e'             => $this->tx->e2e_id,
                'direction'       => $this->tx->direction,
                'method'          => $this->tx->method,
                'created_at'      => $this->tx->created_at,
                'updated_at'      => $this->tx->updated_at,
                'provider_payload'=> $this->tx->provider_payload,
            ];

            $response = Http::post($this->user->webhook_in_url, $payload);

            Log::info("Webhook Pix Create enviado", [
                'user_id' => $this->user->id,
                'status'  => $response->status(),
                'url'     => $this->user->webhook_in_url,
            ]);
        } catch (\Throwable $e) {

            Log::warning("⚠️ Failed webhook (async)", [
                'user_id' => $this->user->id,
                'error'   => $e->getMessage(),
            ]);

            throw $e; // Permite retry + aparece no Horizon
        }
    }
}
