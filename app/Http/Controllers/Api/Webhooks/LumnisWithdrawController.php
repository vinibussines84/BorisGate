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

            // Status vem em caixa alta originalmente
            $status = strtolower(data_get($payload, 'status'));

            // Identificador REAL da transação
            $reference = data_get($payload, 'receipt.0.identifier')
                ?? data_get($payload, 'operation.identifier')
                ?? data_get($payload, 'id');

            if (!$reference) {
                Log::warning('⚠️ Lumnis webhook: missing_reference', [
                    'payload' => $payload,
                ]);

                return response()->json([
                    'ignored' => true,
                    'reason'  => 'missing_reference',
                ]);
            }

            // Processa apenas se aprovado
            if (!in_array($status, ['approved', 'paid'])) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'status_not_approved',
                    'status'  => $status,
                ]);
            }

            $withdraw = Withdraw::where('provider_reference', $reference)->first();

            if (!$withdraw) {
                Log::warning('⚠️ Lumnis webhook: saque não encontrado', [
                    'reference' => $reference,
                    'payload'   => $payload,
                ]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Dados do receipt
            $receipt = $payload['receipt'][0] ?? [];

            // Conversão de centavos ⇒ reais
            $requestedReais  = ((int) data_get($payload, 'requested', 0)) / 100;
            $paidReais       = ((int) data_get($payload, 'paid', 0)) / 100;
            $operationAmount = ((int) data_get($payload, 'operation.amount', 0)) / 100;

            $endtoend        = data_get($receipt, 'endtoend');
            $receiverName    = data_get($receipt, 'receiver_name');
            $receiverBank    = data_get($receipt, 'receiver_bank');
            $receiverIspb    = data_get($receipt, 'receiver_bank_ispb');
            $refusedReason   = data_get($receipt, 'refused_reason');
            $paidAt          = now()->toIso8601String();

            // Atualização idempotente
            if ($withdraw->status !== 'paid') {

                $meta = (array) $withdraw->meta;

                $meta['raw_provider_payload'] = $payload;
                $meta['provider_status']      = $status;
                $meta['requested_reais']      = $requestedReais;
                $meta['paid_reais']           = $paidReais;
                $meta['operation_reais']      = $operationAmount;
                $meta['endtoend']             = $endtoend;
                $meta['identifier']           = $reference;
                $meta['receiver_name']        = $receiverName;
                $meta['receiver_bank']        = $receiverBank;
                $meta['receiver_bank_ispb']   = $receiverIspb;
                $meta['refused_reason']       = $refusedReason;
                $meta['paid_at']              = $paidAt;

                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'amount'       => $requestedReais,
                    'meta'         => $meta,
                ]);

                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {

                    // Remove o campo operation.postback antes do repasse para cliente
                    unset($payload['operation']['postback']);

                    // Converte valores para reais no payload enviado ao cliente
                    $payloadToClient = $payload;
                    $payloadToClient['requested'] = $requestedReais;
                    $payloadToClient['paid']      = $paidReais;
                    $payloadToClient['operation']['amount'] = $operationAmount;

                    // Inclui external_id
                    $payloadToClient['external_id'] = $withdraw->external_id;

                    try {
                        $response = Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data'  => $payloadToClient,
                        ]);

                        Log::info('✅ Webhook withdraw.updated enviado com sucesso', [
                            'user_id'      => $user->id,
                            'url'          => $user->webhook_out_url,
                            'status'       => $response->status(),
                            'body'         => $response->body(),
                            'external_id'  => $withdraw->external_id,
                            'provider_ref' => $reference,
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
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
