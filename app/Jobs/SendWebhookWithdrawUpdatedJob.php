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

    public $tries   = 5;
    public $timeout = 10;

    protected int $userId;
    protected int $withdrawId;
    protected string $status;
    protected string $reference;
    protected array $raw;

    public function __construct(
        int $userId,
        int $withdrawId,
        string $status,
        string $reference,
        array $raw = []
    ) {
        $this->userId     = $userId;
        $this->withdrawId = $withdrawId;
        $this->status     = strtoupper($status);
        $this->reference  = $reference;
        $this->raw        = $raw;

        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $user     = User::find($this->userId);
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$user || !$withdraw) {
            Log::warning('⚠️ withdraw.updated ignorado — registro não encontrado.', [
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        if (!$user->webhook_enabled || !$user->webhook_out_url) {
            Log::info('ℹ️ withdraw.updated ignorado — webhook desabilitado.', [
                'user_id' => $user->id,
            ]);
            return;
        }

        $meta = $withdraw->meta ?? [];

        /**
         * ▶ Monta payload FINAL
         */
        $payload = [
            'event' => 'withdraw.updated',
            'data'  => [
                'id'        => $meta['internal_reference'] ?? $withdraw->id,
                'status'    => $this->status,
                'E2E'       => $meta['e2e'] ?? null,
                'requested' => (float) $withdraw->gross_amount,
                'paid'      => (float) $withdraw->amount,

                'operation' => [
                    'amount'      => (float) $withdraw->amount,
                    'key'         => $withdraw->pixkey,
                    'key_type'    => strtoupper($withdraw->pixkey_type),
                    'description' => 'Withdraw',
                    'details'     => $meta['details'] ?? [],
                ],

                'receipt' => [[
                    'status'             => $this->status,
                    'endtoend'           => $meta['e2e'] ?? null,
                    'identifier'         => $this->reference,
                    'receiver_name'      => $meta['receiver_name'] ?? $user->name,
                    'receiver_bank'      => $meta['receiver_bank'] ?? 'Bank N/A',
                    'receiver_bank_ispb' => $meta['receiver_ispb'] ?? '90400888',

                    // CORREÇÃO DA LÓGICA
                    'refused_reason'     => $this->status === 'FAILED'
                        ? ($this->raw['data']['description'] ?? 'Withdraw Failed')
                        : null,
                ]],

                'external_id' => $withdraw->external_id,
            ],
        ];

        /**
         * ▶ Assinatura HMAC
         */
        $signature = hash_hmac('sha256', json_encode($payload), $user->secretkey);

        try {
            $response = Http::timeout(8)
                ->retry(3, 200)
                ->withHeaders([
                    'X-Signature'      => $signature,
                    'X-Webhook-Event'  => 'withdraw.updated',
                ])
                ->post($user->webhook_out_url, $payload);

            Log::info('📤 webhook withdraw.updated enviado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $response->status(),
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ Falha ao enviar webhook withdraw.updated", [
                'withdraw_id' => $withdraw->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
