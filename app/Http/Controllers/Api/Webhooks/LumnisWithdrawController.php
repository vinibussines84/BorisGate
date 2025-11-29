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
            $data = $request->json()->all();

            $status     = strtoupper(data_get($data, 'status', ''));
            $reference  = data_get($data, 'id');
            $receipt    = data_get($data, 'receipt.0', []);

            // ✅ Processa apenas se status = APPROVED
            if ($status !== 'APPROVED' || !$reference) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'status_not_approved_or_missing_reference',
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
                $withdraw->update([
                    'status' => 'paid',
                    'meta' => array_merge((array) $withdraw->meta, [
                        'endtoend'            => $receipt['endtoend'] ?? null,
                        'identifier'          => $receipt['identifier'] ?? null,
                        'receiver_name'       => $receipt['receiver_name'] ?? null,
                        'receiver_bank'       => $receipt['receiver_bank'] ?? null,
                        'receiver_bank_ispb'  => $receipt['receiver_bank_ispb'] ?? null,
                        'refused_reason'      => $receipt['refused_reason'] ?? null,
                        'webhook_payload'     => $data,
                        'paid_at'             => now()->toDateTimeString(),
                    ]),
                ]);

                // 🚀 Envia webhook de atualização (withdraw.updated)
                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {
                    try {
                        Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data' => [
                                'id'             => $withdraw->id,
                                'status'         => 'paid',
                                'reference'      => $reference,
                                'amount'         => $withdraw->gross_amount ?? $withdraw->amount,
                                'pix_key'        => $withdraw->pixkey,
                                'pix_key_type'   => $withdraw->pixkey_type,
                                'endtoend'       => $receipt['endtoend'] ?? null,
                                'identifier'     => $receipt['identifier'] ?? null,
                                'receiver_name'  => $receipt['receiver_name'] ?? null,
                                'receiver_bank'  => $receipt['receiver_bank'] ?? null,
                                'receiver_ispb'  => $receipt['receiver_bank_ispb'] ?? null,
                                'refused_reason' => $receipt['refused_reason'] ?? null,
                                'paid_at'        => now()->toIso8601String(),
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
