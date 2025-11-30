<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LumnisWithdrawController extends Controller
{
    public function __invoke(Request $request)
    {
        try {

            $payload = $request->json()->all();

            // status: APPROVED / PAID
            $status = strtolower(data_get($payload, 'status'));

            // ID REAL DO SAQUE (sempre vem)
            $reference = data_get($payload, 'id');

            if (!$reference) {
                Log::warning('⚠️ Lumnis webhook sem ID', ['payload' => $payload]);
                return response()->json(['ignored' => true, 'reason' => 'missing_id']);
            }

            // Aceita apenas APPROVED ou PAID
            if (!in_array($status, ['approved', 'paid'])) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'status_not_approved',
                    'status'  => $status
                ]);
            }

            // Tenta localizar o saque pelo provider_reference (id do lote)
            $withdraw = Withdraw::where('provider_reference', $reference)->first();

            // Fallback opcional: alguns registros antigos usaram "identifier"
            if (!$withdraw) {
                $withdraw = Withdraw::where('provider_reference', data_get($payload,'receipt.0.identifier'))->first();
            }

            if (!$withdraw) {
                Log::warning('❌ Lumnis webhook: saque não encontrado', [
                    'reference' => $reference,
                    'identifier' => data_get($payload,'receipt.0.identifier'),
                    'payload'   => $payload,
                ]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Extrai o primeiro receipt
            $receipt = data_get($payload, 'receipt.0', []);

            // Conversões de centavos → reais
            $requested  = ((int) data_get($payload, 'requested', 0)) / 100;
            $paidAmount = ((int) data_get($payload, 'paid', 0)) / 100;
            $opAmount   = ((int) data_get($payload, 'operation.amount', 0)) / 100;

            // Atualiza META
            $meta = (array) $withdraw->meta;

            $meta['raw_provider_payload'] = $payload;
            $meta['endtoend']             = data_get($receipt, 'endtoend');
            $meta['identifier']           = data_get($receipt, 'identifier');
            $meta['receiver_name']        = data_get($receipt, 'receiver_name');
            $meta['receiver_bank']        = data_get($receipt, 'receiver_bank');
            $meta['receiver_bank_ispb']   = data_get($receipt, 'receiver_bank_ispb');
            $meta['refused_reason']       = data_get($receipt, 'refused_reason');
            $meta['paid_at']              = now()->toIso8601String();
            $meta['paid_status']          = $status;

            // Atualização idempotente
            if ($withdraw->status !== 'paid') {

                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'amount'       => $requested, // valor em reais
                    'meta'         => $meta,
                ]);

                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {

                    // Remove o postback antes de repassar ao cliente
                    unset($payload['operation']['postback']);

                    // Payload com valores convertidos
                    $payloadToClient = $payload;
                    $payloadToClient['requested'] = $requested;
                    $payloadToClient['paid']      = $paidAmount;
                    $payloadToClient['operation']['amount'] = $opAmount;
                    $payloadToClient['external_id'] = $withdraw->external_id;

                    try {
                        Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data'  => $payloadToClient,
                        ]);

                    } catch (\Throwable $ex) {
                        Log::warning('⚠️ Falha ao enviar webhook withdraw.updated', [
                            'user_id' => $user->id,
                            'url'     => $user->webhook_out_url,
                            'error'   => $ex->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'received'     => true,
                'status'       => $status,
                'reference'    => $reference,
                'external_id'  => $withdraw->external_id,
                'withdraw_id'  => $withdraw->id,
                'user_id'      => $withdraw->user_id,
            ]);

        } catch (\Throwable $e) {

            Log::error('❌ Erro no webhook Lumnis Withdraw', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
