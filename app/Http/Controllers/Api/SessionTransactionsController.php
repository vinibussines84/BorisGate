<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class SessionTransactionsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Não autenticado.'], 401);
        }

        /* ============================================================
         * PARÂMETROS
         * ============================================================ */
        $search     = trim($request->query('search', ''));
        $statusIn   = strtoupper((string)$request->query('status', 'ALL'));
        $method     = strtoupper((string)$request->query('method', 'ALL'));

        $page    = max(1, (int)$request->query('page', 1));
        $perPage = min(50, max(5, (int)$request->query('perPage', 20)));
        $offset  = ($page - 1) * $perPage;

        /* ============================================================
         * STATUS MAP NORMALIZADO
         * ============================================================ */
        $alias = [
            'EFETIVADO' => ['paga', 'paid', 'approved', 'confirmed'],
            'PENDENTE'  => ['pending', 'pendente', 'processing', 'created'],
            'FALHADO'   => ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled'],
        ];

        /* ============================================================
         * QUERY PIX + QUERY SAQUE UNIFICADAS VIA UNION
         * ============================================================ */

        $pixQ = Transaction::query()
            ->selectRaw("
                id,
                'PIX' as _kind,
                method,
                direction = 'in' as credit,
                amount,
                fee,
                status,
                description,
                txid,
                e2e_id,
                created_at,
                paid_at
            ")
            ->where('user_id', $user->id);

        // Status
        if ($statusIn !== 'ALL' && isset($alias[$statusIn])) {
            $pixQ->whereIn('status', $alias[$statusIn]);
        }

        // Método (somente PIX)
        if ($method !== "ALL" && $method !== "PIX") {
            $pixQ->whereRaw("1 = 0");
        }

        // Busca
        if ($search !== '') {
            $like = "%{$search}%";
            $pixQ->where(function ($q) use ($like) {
                $q->where('id', 'LIKE', $like)
                  ->orWhere('txid', 'LIKE', $like)
                  ->orWhere('e2e_id', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like)
                  ->orWhere('reference', 'LIKE', $like)
                  ->orWhere('external_id', 'LIKE', $like);
            });
        }

        /* ------------------------------------------------------------ */

        $wdQ = Withdraw::query()
            ->selectRaw("
                id,
                'SAQUE' as _kind,
                'SAQUE' as method,
                false as credit,
                gross_amount as amount,
                fee_amount as fee,
                status,
                description,
                null as txid,
                pixkey as e2e_id,
                created_at,
                processed_at as paid_at
            ")
            ->where('user_id', $user->id);

        if ($method !== "ALL" && $method !== "SAQUE") {
            $wdQ->whereRaw("1 = 0");
        }

        if ($statusIn !== 'ALL' && isset($alias[$statusIn])) {
            $wdQ->whereIn('status', $alias[$statusIn]);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $wdQ->where(function ($q) use ($like) {
                $q->where('id', 'LIKE', $like)
                  ->orWhere('pixkey', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like);
            });
        }

        /* ============================================================
         * UNION FINAL
         * ============================================================ */
        $union = $pixQ
            ->unionAll($wdQ)
            ->orderBy('created_at', 'desc');

        /* ========================================
         * PAGINAÇÃO REAL
         * ======================================== */
        $total = $union->count();

        $rows = $union
            ->offset($offset)
            ->limit($perPage)
            ->get();

        /* ========================================
         * NORMALIZAÇÃO LEVE
         * ======================================== */

        $rows = $rows->map(function ($t) use ($user) {

            $raw = strtolower($t->status);
            $statusEnum = TransactionStatus::fromLoose($raw);

            $amount = (float) ($t->amount ?? 0);
            $credit = (bool) $t->credit;

            $fee = (float) ($t->fee ?? 0);

            if ($t->_kind === "PIX" && $fee <= 0) {
                // Entrada
                if ($credit && $user->tax_in_enabled) {
                    $fee = $user->tax_in_mode === 'fixo'
                        ? (float)$user->tax_in_fixed
                        : round($amount * ($user->tax_in_percent / 100), 2);
                }
                // Saída via PIX (raríssimo)
                if (!$credit && $user->tax_out_enabled) {
                    $fee = $user->tax_out_mode === 'fixo'
                        ? (float)$user->tax_out_fixed
                        : round($amount * ($user->tax_out_percent / 100), 2);
                }
            }

            $net = $credit ? ($amount - $fee) : -($amount + $fee);

            return [
                '_kind'       => $t->_kind,
                'id'          => $t->id,
                'credit'      => $credit,
                'amount'      => $amount,
                'fee'         => round($fee, 2),
                'net'         => round($net, 2),
                'method'      => strtoupper($t->method),
                'status'      => $statusEnum->value,
                'status_label'=> $statusEnum->label(),
                'description' => $t->description,
                'txid'        => $t->txid,
                'e2e_id'      => $t->e2e_id,
                'createdAt'   => $t->created_at?->toIso8601String(),
                'paidAt'      => $t->paid_at?->toIso8601String(),
            ];
        });

        /* ========================================
         * TOTAIS
         * ======================================== */
        $paid = $rows->filter(fn ($r) => $r['status'] === "paga");

        return response()->json([
            'success'      => true,
            'page'         => $page,
            'perPage'      => $perPage,
            'count'        => $rows->count(),
            'totalItems'   => $total,
            'transactions' => $rows->values(),

            'totals' => [
                'volume_bruto'   => round($paid->sum('amount'), 2),
                'volume_liquido' => round($paid->sum('net'), 2),
                'taxa_total'     => round($paid->sum('fee'), 2),
            ]
        ]);
    }
}
