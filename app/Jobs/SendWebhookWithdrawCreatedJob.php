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

    /**
     * Cria uma nova instância do job.
     */
    public function __construct(int $userId, int $withdrawId, string $status, ?string $providerReference = null)
    {
        $this->userId = $userId;
        $this->withdrawId = $withdrawId;
        $this->status = $status;
        $this->providerReference = $providerReference;

        $this->onQueue('webhooks');
    }

    /**
     * Executa o job.
     */
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

        try {
            if (!$user->webhook_enabled || !$user->webhook_out_url) {
                Log::info("ℹ️ Webhook de saque ignorado: usuário sem webhook configurado.", [
                    'user_id' => $user->id,
                    'withdraw_id' => $withdraw->id,
                ]);
                return;
            }

            $payload = [
                'event' => 'withdraw.created',
                'data' => [
                    'id'            => $withdraw->id,
                    'external_id'   => $withdraw->external_id,
                    'amount'        => $withdraw->gross_amount,
                    'liquid_amount' => $withdraw->amount,
                    'pix_key'       => $withdraw->pixkey,
                    'pix_key_type'  => $withdraw->pixkey_type,
                    'status'        => $this->status,
                    'reference'     => $this->providerReference,
                ],
            ];

            $response = Http::timeout(10)->post($user->webhook_out_url, $payload);

            Log::info("✅ Webhook de saque enviado", [
                'user_id' => $user->id,
                'withdraw_id' => $withdraw->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("⚠️ Falha ao enviar webhook de saque", [
                'user_id' => $user->id,
                'withdraw_id' => $withdraw->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
