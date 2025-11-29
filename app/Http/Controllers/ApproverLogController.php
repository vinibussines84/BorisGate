<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApproverLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->dashrash != 1) {
            abort(403, 'Acesso negado');
        }

        // FILTROS
        $query = Transaction::where('status', 'under_review')
            ->where('provider', '!=', 'pluggou');

        if ($request->filled('min')) {
            $query->where('amount', '>=', (float)$request->min);
        }

        if ($request->filled('max')) {
            $query->where('amount', '<=', (float)$request->max);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('txid')) {
            $query->where('provider_transaction_id', 'like', "%{$request->txid}%");
        }

        // Paginação + formatação de data
        $pending = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($tx) {
                $tx->formatted_date = $tx->created_at->format('d/m/Y H:i');
                return $tx;
            })
            ->withQueryString();

        // ✅ Soma total dos valores pendentes
        $totalValue = (clone $query)->sum('amount');

        return Inertia::render('AproverLog', [
            'pending' => $pending,
            'total_value' => $totalValue,
            'filters' => $request->only(['min', 'max', 'from', 'to', 'txid']),
            'auth' => ['user' => $user]
        ]);
    }

    public function approve($id, Request $request)
    {
        if ($request->user()->dashrash != 1) abort(403);

        $tx = Transaction::findOrFail($id);

        if ($tx->status !== 'under_review') {
            return back()->with('error', 'Transação inválida.');
        }

        $tx->status = 'paga';
        $tx->paid_at = now();
        $tx->save();

        return back()->with('success', 'Transação aprovada!');
    }

    public function reject($id, Request $request)
    {
        if ($request->user()->dashrash != 1) abort(403);

        $tx = Transaction::findOrFail($id);

        if ($tx->status !== 'under_review') {
            return back()->with('error', 'Transação inválida.');
        }

        $tx->status = 'rejected';
        $tx->rejected_reason = $request->reason ?? null;
        $tx->save();

        return back()->with('success', 'Transação rejeitada!');
    }
}
