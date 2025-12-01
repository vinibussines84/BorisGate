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

    public function __construct(
        int $userId,
        int $withdrawId,
        string $status,
        string $reference,
        array $raw = []
    ) {
        $this->userId = $userId;
        $this->withdrawId = $withdrawId;
        $this->status = strtoupper($status);
        $this->reference = $reference;
        $this->raw = $raw;

        // fila separada
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$user || !$withdraw) {
            Log::warning('⚠️ Webhook OUT ignorado: usuário ou saque não encontrados.', [
                'user_id'     => $this->userId,
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        try {
            /* ============================================================
             * 1️⃣ Usuário não configurou webhook → ignorar
             * ============================================================ */
            if (!$user->webhook_enabled || !$user->webhook_out_url) {
                Log::info('ℹ️ Webhook OUT ignorado (usuário sem webhook configurado).', [
                    'user_id' => $user->id,
                ]);
                return;
            }

            /* ============================================================
             * 2️⃣ Montando payload final
             * ============================================================ */

            $e2e = $withdraw->meta['e2e'] ?? null;
            $receiverName = $withdraw->meta['receiver_name'] ?? $user->name;
            $receiverBank = $withdraw->meta['receiver_bank'] ?? 'EquitPay';
            $receiverIspb = $withdraw->meta['receiver_ispb'] ?? '90400888';

            $failedReason =
                $this->status !== 'APPROVED'
                    ? ($this->raw['data']['description'] ?? 'Withdraw Failed')
                    : null;

            $payload = [
                'id'        => $this->reference,
                'status'    => $this->status,                   // APPROVED ou FAILED
                'requested' => (float) $withdraw->gross_amount, // valor solicitado
                'paid'      => (float) $withdraw->amount,       // valor líquido
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
                    'status'             => $this->status,
                    'endtoend'           => $e2e,
                    'identifier'         => $this->reference,
                    'receiver_name'      => $receiverName,
                    'receiver_bank'      => $receiverBank,
                    'receiver_bank_ispb' => $receiverIspb,
                    'refused_reason'     => $failedReason,
                ]],
                'external_id' => $withdraw->external_id,
            ];

            /* ============================================================
             * 3️⃣ Enviar webhook OUT para o cliente
             * ============================================================ */

            $response = Http::timeout(12)->post($user->webhook_out_url, [
                'event' => 'withdraw.updated',
                'data'  => $payload,
            ]);

            Log::info('📤 Webhook OUT enviado (withdraw.updated)', [
                'user_id'     => $user->id,
                'withdraw_id' => $withdraw->id,
                'status'      => $this->status,
                'http_code'   => $response->status(),
            ]);

        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook OUT (withdraw.updated)', [
                'withdraw_id' => $withdraw->id,
                'user_id'     => $user->id ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
