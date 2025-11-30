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

    protected int $userId;
    protected int $withdrawId;
    protected string $status;
    protected string $reference;
    protected array $raw;

    public function __construct(int $userId, int $withdrawId, string $status, string $reference, array $raw = [])
    {
        $this->userId = $userId;
        $this->withdrawId = $withdrawId;
        $this->status = strtoupper($status);
        $this->reference = $reference;
        $this->raw = $raw;

        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$user || !$withdraw) {
            Log::warning('⚠️ Webhook OUT ignorado: usuário ou saque não encontrados.', [
                'user_id' => $this->userId,
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        try {
            if (!$user->webhook_enabled || !$user->webhook_out_url) {
                Log::info('ℹ️ Webhook OUT ignorado (usuário sem webhook configurado).', [
                    'user_id' => $user->id,
                ]);
                return;
            }

            $e2e = $withdraw->meta['e2e'] ?? null;

            $payload = [
                'id'        => $this->reference,
                'status'    => $this->status,
                'requested' => (float) $withdraw->gross_amount,
                'paid'      => (float) $withdraw->amount,
                'operation' => [
                    'amount'      => (float) $withdraw->gross_amount,
                    'key'         => $withdraw->pixkey,
                    'key_type'    => strtoupper($withdraw->pixkey_type),
                    'description' => 'Withdraw',
                    'details'     => [
                        'name'      => $user->name,
                        'document'  => $user->cpf_cnpj ?? '00000000000',
                    ],
                ],
                'receipt' => [[
                    'status'            => $this->status,
                    'endtoend'          => $e2e,
                    'identifier'        => $this->reference,
                    'receiver_name'     => $withdraw->meta['receiver_name'] ?? $user->name,
                    'receiver_bank'     => $withdraw->meta['receiver_bank'] ?? 'EquitPay',
                    'receiver_bank_ispb'=> $withdraw->meta['receiver_ispb'] ?? '90400888',
                    'refused_reason'    => $this->status === 'APPROVED'
                        ? 'Withdraw Successful'
                        : ($this->raw['data']['description'] ?? null),
                ]],
                'external_id' => $withdraw->external_id,
            ];

            $response = Http::timeout(10)->post($user->webhook_out_url, [
                'event' => 'withdraw.updated',
                'data'  => $payload,
            ]);

            Log::info('📤 Webhook OUT (withdraw.updated) enviado com sucesso', [
                'user_id'     => $user->id,
                'withdraw_id' => $withdraw->id,
                'status'      => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook OUT (withdraw.updated)', [
                'withdraw_id' => $withdraw->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
