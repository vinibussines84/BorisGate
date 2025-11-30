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

            $status = strtolower(data_get($payload, 'status'));

            // Pega o MESMO id usado na criação
            $reference = data_get($payload, 'id');

            if (!$reference) {
                return response()->json(['ignored' => true, 'reason' => 'missing_id']);
            }

            if (!in_array($status, ['approved', 'paid'])) {
                return response()->json(['ignored' => true, 'reason' => 'status_not_approved']);
            }

            $withdraw = Withdraw::where('provider_reference', $reference)->first();

            if (!$withdraw) {
                Log::warning('Lumnis webhook: withdraw not found', [
                    'reference' => $reference,
                ]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            $receipt = data_get($payload, 'receipt.0', []);

            $requested = ((int) data_get($payload, 'requested')) / 100;
            $paid      = ((int) data_get($payload, 'paid')) / 100;
            $opAmount  = ((int) data_get($payload, 'operation.amount')) / 100;

            $meta = (array) $withdraw->meta;

            $meta['raw_provider_payload'] = $payload;
            $meta['endtoend']             = data_get($receipt, 'endtoend');
            $meta['identifier']           = data_get($receipt, 'identifier');
            $meta['receiver_name']        = data_get($receipt, 'receiver_name');
            $meta['receiver_bank']        = data_get($receipt, 'receiver_bank');
            $meta['receiver_bank_ispb']   = data_get($receipt, 'receiver_bank_ispb');
            $meta['refused_reason']       = data_get($receipt, 'refused_reason');
            $meta['paid_at']              = now()->toIso8601String();

            if ($withdraw->status !== 'paid') {

                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'amount'       => $requested,
                    'meta'         => $meta,
                ]);

                $user = User::find($withdraw->user_id);

                if ($user && $user->webhook_enabled && $user->webhook_out_url) {
                    unset($payload['operation']['postback']);

                    $payloadToClient = $payload;
                    $payloadToClient['requested'] = $requested;
                    $payloadToClient['paid']      = $paid;
                    $payloadToClient['operation']['amount'] = $opAmount;
                    $payloadToClient['external_id'] = $withdraw->external_id;

                    try {
                        Http::timeout(10)->post($user->webhook_out_url, [
                            'event' => 'withdraw.updated',
                            'data'  => $payloadToClient,
                        ]);
                    } catch (\Throwable $e) {}
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
            Log::error('Webhook Lumnis error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
