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

class SendWebhookWithdrawUpdatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected User $user;
    protected Withdraw $withdraw;
    protected string $status;
    protected string $reference;
    protected array $raw;

    /**
     * Cria uma nova instância do job.
     */
    public function __construct(User $user, Withdraw $withdraw, string $status, string $reference, array $raw = [])
    {
        $this->user = $user;
        $this->withdraw = $withdraw;
        $this->status = strtoupper($status);
        $this->reference = $reference;
        $this->raw = $raw;

        $this->onQueue('webhooks');
    }

    /**
     * Executa o job.
     */
    public function handle(): void
    {
        try {
            if (!$this->user->webhook_enabled || !$this->user->webhook_out_url) {
                Log::info('ℹ️ Webhook OUT ignorado (usuário sem webhook configurado).', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            $e2e = $this->withdraw->meta['e2e'] ?? null;

            $payload = [
                'id'        => $this->reference,
                'status'    => $this->status,
                'requested' => (float) $this->withdraw->gross_amount,
                'paid'      => (float) $this->withdraw->amount,
                'operation' => [
                    'amount'      => (float) $this->withdraw->gross_amount,
                    'key'         => $this->withdraw->pixkey,
                    'key_type'    => strtoupper($this->withdraw->pixkey_type),
                    'description' => 'Withdraw',
                    'details'     => [
                        'name'      => $this->user->name,
                        'document'  => $this->user->cpf_cnpj ?? '00000000000',
                    ],
                ],
                'receipt' => [[
                    'status'            => $this->status,
                    'endtoend'          => $e2e,
                    'identifier'        => $this->reference,
                    'receiver_name'     => $this->withdraw->meta['receiver_name'] ?? $this->user->name,
                    'receiver_bank'     => $this->withdraw->meta['receiver_bank'] ?? 'EquitPay',
                    'receiver_bank_ispb'=> $this->withdraw->meta['receiver_ispb'] ?? '90400888',
                    'refused_reason'    => $this->status === 'APPROVED'
                        ? 'Withdraw Successful'
                        : ($this->raw['data']['description'] ?? null),
                ]],
                'external_id' => $this->withdraw->external_id,
            ];

            $response = Http::timeout(10)->post($this->user->webhook_out_url, [
                'event' => 'withdraw.updated',
                'data'  => $payload,
            ]);

            Log::info('📤 Webhook OUT (withdraw.updated) enviado com sucesso', [
                'user_id'     => $this->user->id,
                'withdraw_id' => $this->withdraw->id,
                'status'      => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook OUT (withdraw.updated)', [
                'withdraw_id' => $this->withdraw->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
