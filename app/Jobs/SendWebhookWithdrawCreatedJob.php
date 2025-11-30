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

    protected User $user;
    protected Withdraw $withdraw;
    protected string $status;
    protected ?string $providerReference;

    /**
     * Cria uma nova instância do job.
     */
    public function __construct(User $user, Withdraw $withdraw, string $status, ?string $providerReference = null)
    {
        $this->user = $user;
        $this->withdraw = $withdraw;
        $this->status = $status;
        $this->providerReference = $providerReference;

        // Define a fila específica
        $this->onQueue('webhooks');
    }

    /**
     * Executa o job.
     */
    public function handle(): void
    {
        try {
            if (!$this->user->webhook_enabled || !$this->user->webhook_out_url) {
                Log::info("ℹ️ Webhook de saque ignorado: usuário sem webhook configurado.", [
                    'user_id' => $this->user->id,
                    'withdraw_id' => $this->withdraw->id,
                ]);
                return;
            }

            $payload = [
                'event' => 'withdraw.created',
                'data' => [
                    'id'            => $this->withdraw->id,
                    'external_id'   => $this->withdraw->external_id,
                    'amount'        => $this->withdraw->gross_amount,
                    'liquid_amount' => $this->withdraw->amount,
                    'pix_key'       => $this->withdraw->pixkey,
                    'pix_key_type'  => $this->withdraw->pixkey_type,
                    'status'        => $this->status,
                    'reference'     => $this->providerReference,
                ],
            ];

            $response = Http::post($this->user->webhook_out_url, $payload);

            Log::info("✅ Webhook de saque enviado", [
                'user_id' => $this->user->id,
                'withdraw_id' => $this->withdraw->id,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("⚠️ Falha ao enviar webhook de saque", [
                'user_id' => $this->user->id,
                'withdraw_id' => $this->withdraw->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
