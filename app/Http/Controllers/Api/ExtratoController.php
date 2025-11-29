<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Enums\TransactionStatus;
use Illuminate\Http\Request;

class ExtratoController extends Controller
{
    /**
     * GET /api/extrato
     * Retorna as transações do usuário com paginação, filtros e performance alta
     */
    public function transactions(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado.',
            ], 401);
        }

        /* ---------------------------------------
         * PARÂMETROS
         * --------------------------------------- */
        $search   = trim($request->query('search', ''));
        $statusIn = strtoupper($request->query('status', 'ALL'));
        $page     = max(1, (int)$request->query('page', 1));
        $perPage  = min(50, max(5, (int)$request->query('perPage', 20)));
        $offset   = ($page - 1) * $perPage;

        /* ---------------------------------------
         * NORMALIZAÇÃO STATUS
         * --------------------------------------- */
        $alias = [
            'EFETIVADO' => ['paga', 'paid', 'approved', 'confirmed'],
    'PENDENTE'  => ['pending', 'pendente', 'processing', 'created', 'under_review'],
            'FALHADO'   => ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled'],
        ];

        /* ---------------------------------------
         * PIX QUERY (UNIFICADO)
         * --------------------------------------- */
        $pixQ = Transaction::query()
            ->selectRaw("
                id,
                'PIX' as _kind,
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

        if ($statusIn !== 'ALL' && isset($alias[$statusIn])) {
            $pixQ->whereIn('status', $alias[$statusIn]);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $pixQ->where(function ($q) use ($like) {
                $q->where('id', 'LIKE', $like)
                  ->orWhere('txid', 'LIKE', $like)
                  ->orWhere('e2e_id', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like);
            });
        }

        /* ---------------------------------------
         * SAQUE QUERY (UNIFICADO)
         * --------------------------------------- */
        $wdQ = Withdraw::query()
            ->selectRaw("
                id,
                'SAQUE' as _kind,
                false as credit,
                gross_amount as amount,
                fee_amount as fee,
                status,
                description,
                pixkey as txid,
                null as e2e_id,
                created_at,
                processed_at as paid_at
            ")
            ->where('user_id', $user->id);

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

        /* ---------------------------------------
         * UNION ALL + ORDER + PAGINAÇÃO REAL
         * --------------------------------------- */
        $union = $pixQ
            ->unionAll($wdQ)
            ->orderBy('created_at', 'desc');

        $total = $union->count();

        $rows = $union
            ->offset($offset)
            ->limit($perPage)
            ->get();

        /* ---------------------------------------
         * NORMALIZAÇÃO FINAL LEVE
         * --------------------------------------- */
        $rows = $rows->map(function ($t) {

            $statusEnum = TransactionStatus::fromLoose($t->status);

            return [
                'id'        => $t->id,
                '_kind'     => $t->_kind,
                'credit'    => (bool) $t->credit,
                'amount'    => (float) $t->amount,
                'fee'       => round((float) $t->fee, 2),
                'net'       => (bool)$t->credit
                                ? (float)$t->amount - (float)$t->fee
                                : -(float)$t->amount,

                // labels
                'status'       => $statusEnum->value,
                'status_label' => $statusEnum->label(),

                // extras do frontend
                'txid'        => $t->txid,
                'e2e'         => $t->e2e_id,
                'description' => $t->description,

                // datas
                'createdAt' => optional($t->created_at)->toIso8601String(),
                'paidAt'    => optional($t->paid_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'success'      => true,
            'page'         => $page,
            'perPage'      => $perPage,
            'count'        => $rows->count(),
            'totalItems'   => $total,
            'transactions' => $rows->values(),
        ]);
    }
}
