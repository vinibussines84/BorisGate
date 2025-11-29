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

            // O provider envia tudo dentro de "data"
            $data = data_get($payload, 'data', []);

            $status    = strtolower(data_get($data, 'status', ''));
            $reference = data_get($data, 'id');

            // Dados enviados pelo provider
            $endtoend       = data_get($data, 'endtoend');
            $identifier     = data_get($data, 'identifier');
            $receiverName   = data_get($data, 'receiver_name');
            $receiverBank   = data_get($data, 'receiver_bank');
            $receiverIspb   = data_get($data, 'receiver_ispb') ?? data_get($data, 'receiver_bank_ispb');
            $refusedReason  = data_get($data, 'refused_reason');
            $paidAt         = data_get($data, 'paid_at') ?? now()->toIso8601String();

            // 📌 Provider envia: "paid" – então usamos isso
            if ($status !== 'paid' || !$reference) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'status_not_paid_or_missing_reference',
                ]);
            }

            /** @var Withdraw|null $withdraw */
            $withdraw = Withdraw::query()
                ->where('provider_reference', $reference)
                ->first();

            if (!$withdraw) {
                Log::warning('⚠️ Lumnis webhook: saque não encontrado', [
                    'reference' => $reference,
                ]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // ⚙️ Atualiza como pago (evita duplicidade)
            if ($withdraw->status !== 'paid') {

                $meta = (array) $withdraw->meta;

                $meta['endtoend']           = $endtoend;
                $meta['identifier']         = $identifier;
                $meta['receiver_name']      = $receiverName;
                $meta['receiver_bank']      = $receiverBank;
                $meta['receiver_bank_ispb'] = $receiverIspb;
                $meta['refused_reason']     = $refusedReason;
                $meta['webhook_payload']    = $payload;
                $meta['paid_at']            = $paidAt;

                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'meta'         => $meta,
                ]);

                // 🚀 Envia webhook de atualização (withdraw.updated)
                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {
                    try {
                        Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data'  => [
                                'id'             => $withdraw->id,
                                'status'         => 'paid',
                                'reference'      => $reference,
                                'amount'         => $withdraw->gross_amount ?? $withdraw->amount,
                                'pix_key'        => $withdraw->pixkey,
                                'pix_key_type'   => $withdraw->pixkey_type,
                                'endtoend'       => $endtoend,
                                'identifier'     => $identifier,
                                'receiver_name'  => $receiverName,
                                'receiver_bank'  => $receiverBank,
                                'receiver_ispb'  => $receiverIspb,
                                'refused_reason' => $refusedReason,
                                'paid_at'        => $paidAt,
                            ],
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
                'status'    => 'paid',
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
