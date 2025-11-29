<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class BalanceController extends Controller
{
    public function available(Request $request)
    {
        // Headers obrigatórios
        $authKey   = $request->header('X-Auth-Key');
        $secretKey = $request->header('X-Secret-Key');

        if (!$authKey || !$secretKey) {
            return response()->json([
                'success' => false,
                'message' => 'Missing authentication headers.',
            ], 401);
        }

        // MESMOS CAMPOS DO TransactionPixController!!!
        $user = User::where('authkey', $authKey)
                    ->where('secretkey', $secretKey)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'user_id'          => $user->id,
            'status'           => 'success',
            'amount_available' => (float) ($user->amount_available ?? 0),
            'amount_retained'  => (float) ($user->amount_retained ?? 0),
            'blocked_amount'   => (float) ($user->blocked_amount ?? 0),
        ]);
    }
}
