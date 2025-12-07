<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $alias = [
            'EFETIVADO' => ['paga', 'paid', 'approved', 'confirmed', 'completed'],
            'PENDENTE'  => ['pending', 'pendente', 'processing', 'created', 'under_review'],
            'FALHADO'   => ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled'],
        ];

        // 🔥 Somente PIX de entrada
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
                e2e_id,
                created_at,
                paid_at
            ")
            ->where('user_id', $user->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix');

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

        $total = (clone $pixQ)->count();

        /*
        |--------------------------------------------------------------------------
        | ❗ ORDEM CORRETA
        | Nunca ordenar por paid_at!
        |--------------------------------------------------------------------------
        */
        $rows = $pixQ
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        /* =========================================================================================== 
           FORMATAÇÃO FINAL — SEM ALTERAR HORÁRIO
        =========================================================================================== */
        $rows = $rows->map(function ($t) {

            $statusEnum = TransactionStatus::fromLoose($t->status);

            // created_at → ISO com fuso
            $createdAt = $t->created_at
                ? Carbon::parse($t->created_at)->tz('America/Sao_Paulo')->format('Y-m-d\TH:i:sP')
                : null;

            // paid_at → manter EXATAMENTE o horário salvo
            $paidAt = null;

            if ($t->paid_at) {
                // Detecta se contém timezone
                $str = (string)$t->paid_at;

                if (preg_match('/[+-]\d{2}:\d{2}$/', $str)) {
                    // já tem timezone → enviar como veio
                    $paidAt = $str;
                } else {
                    // sem timezone → assumir SP e formatar
                    $paidAt = Carbon::parse($str, 'America/Sao_Paulo')->format('Y-m-d\TH:i:sP');
                }
            }

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
                'createdAt'     => $createdAt,
                'paidAt'        => $paidAt,
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
