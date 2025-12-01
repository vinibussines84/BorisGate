<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWebhookWithdrawUpdatedJob;

class PodPayWithdrawWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $payload = $request->json()->all();

            Log::info('📩 Webhook PodPay recebido', ['payload' => $payload]);

            /* ============================================================
             * 1️⃣ Extrair dados essenciais
             * ============================================================ */
            $status      = strtoupper(data_get($payload, 'data.status', 'UNKNOWN'));
            $description = strtolower((string) data_get($payload, 'data.description', ''));
            $historyMsg  = strtolower((string) data_get($payload, 'data.history.0.message', ''));

            /* ============================================================
             * 2️⃣ Referência = provider_reference
             * ============================================================ */
            $reference = (string) data_get($payload, 'objectId');

            if (!$reference) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /* ============================================================
             * 3️⃣ Localizar o saque
             * ============================================================ */
            $withdraw = Withdraw::where('provider_reference', $reference)->first();

            if (!$withdraw) {
                Log::warning('⚠️ PodPay webhook: withdraw não encontrado', [
                    'reference' => $reference,
                ]);
                return response()->json(['ignored' => true]);
            }

            $user = User::find($withdraw->user_id);

            /* ============================================================
             * 4️⃣ Idempotência
             * ============================================================ */
            if (in_array($withdraw->status, ['paid', 'failed'])) {
                Log::info('ℹ️ Webhook PodPay ignorado (já finalizado).', [
                    'withdraw_id' => $withdraw->id,
                    'status'      => $withdraw->status,
                ]);
                return response()->json(['ignored' => true]);
            }

            /* ============================================================
             * 5️⃣ REGRAS DE STATUS → detectar falha, processamento ou sucesso
             * ============================================================ */

            // CANCELADO pela PodPay (duas grafias)
            $isCanceled = in_array($status, ['CANCELLED', 'CANCELED']);

            // Falhas reconhecidas
            $isFailed =
                $isCanceled ||
                str_starts_with($description, 'failed') ||
                str_contains($description, 'cancel') ||
                str_contains($historyMsg, 'não encontramos') ||
                in_array($status, ['FAILED', 'ERROR']);

            // PROCESSING → somente ignorar
            $isProcessing = $status === 'PROCESSING' && !$isFailed;

            if ($isProcessing) {
                Log::info('⏸ Webhook PodPay ignorado (PROCESSING).');
                return response()->json(['ignored' => true]);
            }

            /* ============================================================
             * 6️⃣ CASO DE FALHA → estornar saldo + marcar failed
             * ============================================================ */
            if ($isFailed) {
                DB::transaction(function () use ($withdraw, $user, $payload) {
                    $u = User::where('id', $user->id)->lockForUpdate()->first();
                    $u->amount_available += $withdraw->gross_amount;
                    $u->save();

                    $meta = $withdraw->meta ?? [];
                    $meta['podpay_failed_payload'] = $payload;
                    $meta['failed_at'] = now();

                    $withdraw->update([
                        'status' => 'failed',
                        'meta'   => $meta,
                    ]);
                });

                // Envia webhook OUT
                SendWebhookWithdrawUpdatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    'FAILED',
                    $reference,
                    $payload
                );

                Log::error('❌ Saque PodPay marcado como FAILED', [
                    'withdraw_id' => $withdraw->id,
                    'reason'      => $description ?: $historyMsg,
                ]);

                return response()->json(['success' => true, 'status' => 'failed']);
            }

            /* ============================================================
             * 7️⃣ CASO DE SUCESSO → COMPLETED = pago
             * ============================================================ */
            $isCompleted = $status === 'COMPLETED';

            if ($isCompleted) {
                $e2e = $this->generatePixE2E($withdraw);

                $meta = $withdraw->meta ?? [];
                $meta['podpay_success_payload'] = $payload;
                $meta['e2e'] = $e2e;
                $meta['paid_at'] = now();

                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'meta'         => $meta,
                ]);

                Log::info('✅ Saque PodPay marcado como PAGO', [
                    'withdraw_id' => $withdraw->id,
                    'reference'   => $reference,
                    'e2e'         => $e2e,
                ]);

                SendWebhookWithdrawUpdatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    'APPROVED',
                    $reference,
                    $payload
                );

                return response()->json(['success' => true, 'status' => 'paid']);
            }

            /* ============================================================
             * 8️⃣ Se chegar aqui → status não reconhecido
             * ============================================================ */
            Log::warning('⚠️ Webhook PodPay status desconhecido', ['status' => $status]);
            return response()->json(['ignored' => true]);

        } catch (\Throwable $e) {
            Log::error('🚨 Erro no Webhook PodPay', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }

    /**
     * 🔢 Gerar E2E PIX (SOMENTE PARA SAQUES PAGOS)
     */
    private function generatePixE2E(Withdraw $withdraw): string
    {
        $ispb = '90400888';
        $timestamp = now()->format('YmdHis');
        $seq = str_pad($withdraw->id, 9, '0', STR_PAD_LEFT);
        return "E{$ispb}{$timestamp}{$seq}";
    }
}
