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

            // Status vem na raiz
            $status    = strtolower(data_get($payload, 'status'));
            $reference = data_get($payload, 'id');

            if (!$reference) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'missing_reference',
                ]);
            }

            // Provider retorna "APPROVED"
            if (!in_array($status, ['approved', 'paid'])) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'status_not_approved',
                ]);
            }

            /** @var Withdraw|null $withdraw */
            $withdraw = Withdraw::where('provider_reference', $reference)->first();

            if (!$withdraw) {
                Log::warning('⚠️ Lumnis webhook: saque não encontrado', [
                    'reference' => $reference,
                    'payload'   => $payload,
                ]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // RECEIPT
            $receipt = $payload['receipt'][0] ?? [];

            // Conversão de centavos → reais
            $requestedReais = data_get($payload, 'requested') / 100; // 1000 → 10.00
            $paidReais      = data_get($payload, 'paid') / 100;      // 1000 → 10.00
            $operationAmount = data_get($payload, 'operation.amount') / 100;

            // Dados do receipt
            $endtoend       = data_get($receipt, 'endtoend');
            $identifier     = data_get($receipt, 'identifier');
            $receiverName   = data_get($receipt, 'receiver_name');
            $receiverBank   = data_get($receipt, 'receiver_bank');
            $receiverIspb   = data_get($receipt, 'receiver_bank_ispb');
            $refusedReason  = data_get($receipt, 'refused_reason');
            $paidAt         = now()->toIso8601String();

            // 🔥 Atualiza o saque como pago
            if ($withdraw->status !== 'paid') {

                $meta = (array) $withdraw->meta;

                $meta['raw_provider_payload'] = $payload;  // mantemos tudo original
                $meta['requested_reais']      = $requestedReais;
                $meta['paid_reais']           = $paidReais;
                $meta['operation_reais']      = $operationAmount;
                $meta['endtoend']             = $endtoend;
                $meta['identifier']           = $identifier;
                $meta['receiver_name']        = $receiverName;
                $meta['receiver_bank']        = $receiverBank;
                $meta['receiver_bank_ispb']   = $receiverIspb;
                $meta['refused_reason']       = $refusedReason;
                $meta['paid_at']              = $paidAt;

                // Atualiza saque com valores em reais
                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'amount'       => $requestedReais,
                    'meta'         => $meta,
                ]);

                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {

                    // Remove o campo operation.postback
                    if (isset($payload['operation']['postback'])) {
                        unset($payload['operation']['postback']);
                    }

                    // Alteramos o payload enviado para o cliente para valores em reais
                    $payloadToClient = $payload;

                    $payloadToClient['requested'] = $requestedReais;
                    $payloadToClient['paid']      = $paidReais;
                    $payloadToClient['operation']['amount'] = $operationAmount;

                    try {

                        $response = Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data'  => $payloadToClient,
                        ]);

                        Log::info('Webhook withdraw.updated enviado com sucesso', [
                            'user_id' => $user->id,
                            'url'     => $user->webhook_out_url,
                            'status'  => $response->status(),
                            'body'    => $response->body(),
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
                'received'  => true,
                'status'    => 'approved',
                'reference' => $reference,
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
