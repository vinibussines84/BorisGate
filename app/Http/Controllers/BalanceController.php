<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BalanceController extends Controller
{
    public function get(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'X-Public-Key' => config('services.pluggou.public_key'),
                'X-Secret-Key' => config('services.pluggou.secret_key'),
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(config('services.pluggou.timeout'))
            ->get(config('services.pluggou.base_url') . '/withdrawals/balance');

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro na API da Pluggou',
                    'response' => $response->json(),
                ], $response->status());
            }

            return response()->json($response->json(), 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Erro ao consultar saldo.',
                'error' => $e->getMessage(),
            ], 500);

        }
    }
}
