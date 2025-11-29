<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Enums\TransactionStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', TransactionStatus::MED->value)
            ->latest('created_at')
            ->paginate(20, [
                'id',
                'amount',
                'fee',
                'status',
                'method',
                'e2e_id',
                'description', // ✅ motivo
                'created_at',
                'paid_at',
            ]);

        $countMed = Transaction::where('user_id', $user->id)
            ->where('status', TransactionStatus::MED->value)
            ->count();

        return Inertia::render('Med/Index', [
            'transactions' => $transactions,
            'totalMed'     => $countMed,
        ]);
    }
}
