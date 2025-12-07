<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class ExtratoController extends Controller
{
    public function transactions(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }

        $search   = trim($request->query('search', ''));
        $statusIn = strtoupper($request->query('status', 'ALL'));
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = min(50, max(5, (int) $request->query('perPage', 20)));
        $offset   = ($page - 1) * $perPage;

        // Alias de status usados em filtros
        $alias = [
            'EFETIVADO' => ['paga', 'paid', 'approved', 'confirmed', 'completed'],
            'PENDENTE'  => ['pending', 'pendente', 'processing', 'created', 'under_review'],
            'FALHADO'   => ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled'],
        ];

        /*
        |--------------------------------------------------------------------------
        | 🔥 SOMENTE PIX-IN
        |--------------------------------------------------------------------------
        */
        $pixQ = Transaction::query()
            ->selectRaw("
                id,
                'PIX' as _kind,
                true as credit,
                amount,
                fee,
                status,
                description,
                txid,
                COALESCE(e2e_id, endtoend) as e2e_id,
                created_at,
                paid_at
            ")
            ->where('user_id', $user->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix');

        // Filtro por status
        if ($statusIn !== 'ALL' && isset($alias[$statusIn])) {
            $pixQ->whereIn('status', $alias[$statusIn]);
        }

        // Filtro de busca
        if ($search !== '') {
            $like = "%{$search}%";

            $pixQ->where(function ($q) use ($like) {
                $q->where('id', 'LIKE', $like)
                  ->orWhere('txid', 'LIKE', $like)
                  ->orWhere('e2e_id', 'LIKE', $like)
                  ->orWhere('description', 'LIKE', $like);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 🔥 Total + Paginação
        |--------------------------------------------------------------------------
        */
        $total = (clone $pixQ)->count();

        /*
        |--------------------------------------------------------------------------
        | 🔥 ORDENAR PELO HORÁRIO REAL DO PAGAMENTO (CORREÇÃO)
        |--------------------------------------------------------------------------
        |
        | Se paid_at existir → ordenar por paid_at DESC
        | Se ainda não tiver pago → cair para created_at DESC
        |
        | COALESCE(paid_at, created_at) é a forma correta.
        |--------------------------------------------------------------------------
        */

        $rows = $pixQ
            ->orderByRaw("COALESCE(paid_at, created_at) DESC")
            ->offset($offset)
            ->limit($perPage)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 🔥 Formatação final
        |--------------------------------------------------------------------------
        */
        $rows = $rows->map(function ($t) {

            $statusEnum = TransactionStatus::fromLoose($t->status);

            return [
                'id'            => $t->id,
                '_kind'         => 'PIX',
                'credit'        => true,
                'amount'        => (float) $t->amount,
                'fee'           => round((float) $t->fee, 2),
                'net'           => $t->amount - $t->fee,
                'status'        => $statusEnum->value,
                'status_label'  => $statusEnum->label(),
                'txid'          => $t->txid,
                'e2e'           => $t->e2e_id,
                'description'   => $t->description,
                'createdAt'     => optional($t->created_at)->toIso8601String(),
                'paidAt'        => optional($t->paid_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'success'       => true,
            'page'          => $page,
            'perPage'       => $perPage,
            'count'         => $rows->count(),
            'totalItems'    => $total,
            'transactions'  => $rows->values(),
        ]);
    }
}
