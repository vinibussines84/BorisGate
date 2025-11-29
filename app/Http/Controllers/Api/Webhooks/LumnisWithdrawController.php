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

            // 📌 Agora o provider NÃO usa "data", tudo é enviado na raiz do JSON
            $status    = strtolower(data_get($payload, 'status'));
            $reference = data_get($payload, 'id');

            if (!$reference) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'missing_reference',
                ]);
            }

            // 📌 Provider envia status "APPROVED"
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

            // 📌 Receipt real do provider (sempre índice 0)
            $receipt = $payload['receipt'][0] ?? [];

            $endtoend       = data_get($receipt, 'endtoend');
            $identifier     = data_get($receipt, 'identifier');
            $receiverName   = data_get($receipt, 'receiver_name');
            $receiverBank   = data_get($receipt, 'receiver_bank');
            $receiverIspb   = data_get($receipt, 'receiver_bank_ispb');
            $refusedReason  = data_get($receipt, 'refused_reason');
            $paidAt         = now()->toIso8601String();

            // 🚀 Atualiza o saque como pago
            if ($withdraw->status !== 'paid') {

                $meta = (array) $withdraw->meta;
                $meta['raw_provider_payload'] = $payload;
                $meta['endtoend']             = $endtoend;
                $meta['identifier']           = $identifier;
                $meta['receiver_name']        = $receiverName;
                $meta['receiver_bank']        = $receiverBank;
                $meta['receiver_bank_ispb']   = $receiverIspb;
                $meta['refused_reason']       = $refusedReason;
                $meta['paid_at']              = $paidAt;

                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'meta'         => $meta,
                ]);

                // 🔥 Dispara webhook para o cliente
                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {

                    // Remove APENAS o campo operation.postback
                    if (isset($payload['operation']['postback'])) {
                        unset($payload['operation']['postback']);
                    }

                    try {

                        $response = Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data'  => $payload, // 🔥 Dispara EXACTAMENTE o que o provider enviou
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
