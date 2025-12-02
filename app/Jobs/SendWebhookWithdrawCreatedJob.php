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

    protected int $userId;
    protected int $withdrawId;
    protected string $status;
    protected ?string $providerReference;

    public function __construct(int $userId, int $withdrawId, string $status, ?string $providerReference = null)
    {
        $this->userId = $userId;
        $this->withdrawId = $withdrawId;
        $this->status = $status;
        $this->providerReference = $providerReference;

        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$user || !$withdraw) {
            Log::warning('⚠️ Webhook ignorado: usuário ou saque não encontrados.', [
                'user_id' => $this->userId,
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        if (!$user->webhook_enabled || !$user->webhook_out_url) {
            Log::info("ℹ️ Webhook ignorado: usuário sem webhook configurado.", [
                'user_id' => $user->id,
            ]);
            return;
        }

        try {
            $payload = [
                'event' => 'withdraw.created',
                'event_id' => "withdraw_created_{$withdraw->id}",
                'data' => [
                    'id'            => $withdraw->id,
                    'external_id'   => $withdraw->external_id,
                    'amount'        => $withdraw->gross_amount,
                    'liquid_amount' => $withdraw->amount,
                    'pix_key'       => $withdraw->pixkey,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'status'        => $this->status,
                    'reference'     => $this->providerReference,
                ]
            ];

            // Assinatura para segurança
            $signature = hash_hmac('sha256', json_encode($payload), $user->secretkey);

            $response = Http::timeout(5)
                ->retry(3, 150) // tenta 3x
                ->withHeaders([
                    'X-Signature' => $signature,
                    'X-Webhook-Event' => 'withdraw.created',
                ])
                ->post($user->webhook_out_url, $payload);

            Log::info("✅ Webhook withdraw.created enviado", [
                'user_id' => $user->id,
                'withdraw_id' => $withdraw->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ Falha ao enviar webhook withdraw.created", [
                'user_id' => $user->id,
                'withdraw_id' => $withdraw->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
