// app/Http/Controllers/MeBalanceController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MeBalanceController extends Controller
{
    public function __invoke(Request $request)
    {
        $u = $request->user();

        // Retorna nos nomes que teu front espera (ambos, por compatibilidade)
        return response()->json([
            'amount_retained' => (float) $u->amount_retained, // Saldo retido
            'blocked_amount'  => (float) $u->blocked_amount,  // Bloqueio cautelar

            // Compat (se teu front antigo lia retained/blocked):
            'retained'        => (float) $u->amount_retained,
            'blocked'         => (float) $u->blocked_amount,
        ]);
    }
}
