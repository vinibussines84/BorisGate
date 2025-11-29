<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'balances' => [
                'amount_available' => (float) $user->amount_available,
                'amount_retained'  => (float) $user->amount_retained,
                'blocked_amount'   => (float) $user->blocked_amount,
            ],
        ]);
    }

    public function balances(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'amount_available' => (float) $user->amount_available,
                'amount_retained'  => (float) $user->amount_retained,
                'blocked_amount'   => (float) $user->blocked_amount,
            ],
        ]);
    }
}
