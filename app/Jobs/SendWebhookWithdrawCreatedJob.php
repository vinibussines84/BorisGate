<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookWithdrawCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 5;
    public $timeout = 10; // rápido

    protected int $userId;
    protected int $withdrawId;
    protected string $status;
    protected ?string $providerReference;

    public function __construct(
        int $userId,
        int $withdrawId,
        string $status,
        ?string $providerReference = null
    ) {
        $this->userId            = $userId;
        $this->withdrawId        = $withdrawId;
        $this->status            = strtolower($status);
        $this->providerReference = $providerReference;

        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $user     = User::find($this->userId);
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$user || !$withdraw) {
            Log::warning('⚠️ WithdrawCreatedJob ignorado — user/withdraw não encontrados.', [
                'user_id'     => $this->userId,
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        if (!$user->webhook_enabled || !$user->webhook_out_url) {
            Log::info('ℹ️ Webhook withdraw.created ignorado — webhook desabilitado.', [
                'user_id' => $user->id,
            ]);
            return;
        }

        $payload = [
            'event'    => 'withdraw.created',
            'event_id' => "withdraw_created_{$withdraw->id}",
            'data' => [
                'id'            => $withdraw->id,
                'external_id'   => $withdraw->external_id,
                'amount'        => (float) $withdraw->gross_amount,
                'liquid_amount' => (float) $withdraw->amount,
                'pix_key'       => $withdraw->pixkey,
                'pix_key_type'  => $withdraw->pixkey_type,
                'status'        => strtolower($withdraw->status),
                'reference'     => $this->providerReference,
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $user->secretkey);

        try {
            $response = Http::timeout(8)
                ->retry(3, 200)
                ->withHeaders([
                    'X-Signature'      => $signature,
                    'X-Webhook-Event'  => 'withdraw.created',
                ])
                ->post($user->webhook_out_url, $payload);

            Log::info("📤 webhook withdraw.created enviado", [
                'withdraw_id' => $withdraw->id,
                'status'      => $response->status(),
            ]);

        } catch (\Throwable $e) {
            Log::error('❌ Erro ao enviar withdraw.created', [
                'withdraw_id' => $withdraw->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
