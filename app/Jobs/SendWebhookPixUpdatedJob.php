<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhookPixUpdatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tx;

    public function __construct($tx)
    {
        $this->tx = $tx;
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        try {

            $response = Http::post($this->tx->user->webhook_in_url, [
                "type"            => "Pix Update",
                "event"           => "updated",
                "transaction_id"  => $this->tx->id,
                "external_id"     => $this->tx->external_reference,
                "user"            => $this->tx->user->name,
                "amount"          => number_format($this->tx->amount, 2, '.', ''),
                "fee"             => number_format($this->tx->fee, 2, '.', ''),
                "currency"        => $this->tx->currency,
                "status"          => "paga",
                "txid"            => $this->tx->txid,
                "e2e"             => $this->tx->e2e_id,
                "direction"       => $this->tx->direction,
                "method"          => $this->tx->method,
                "created_at"      => $this->tx->created_at,
                "updated_at"      => $this->tx->updated_at,
                "paid_at"         => $this->tx->paid_at,
                "provider_payload"=> $this->tx->provider_payload
            ]);

            Log::info("Webhook Pix Update enviado", [
                'status' => $response->status(),
                'tx_id'  => $this->tx->id,
            ]);

        } catch (\Throwable $e) {

            Log::warning("Webhook Pix Update falhou", [
                'tx_id' => $this->tx->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
