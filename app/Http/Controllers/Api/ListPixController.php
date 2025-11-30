<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Enums\TransactionStatus;
use Illuminate\Support\Facades\DB;

class ListPixController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado.',
            ], 401);
        }

        /* -------------------------------------------------------------
         * PAGINAÇÃO / FILTROS / BUSCA
         * ------------------------------------------------------------- */
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = min(100, (int) $request->input('perPage', 10));
        $offset   = ($page - 1) * $perPage;

        $statusFilter = $request->input('status');
        $search       = trim($request->input('search', ''));

        /* -------------------------------------------------------------
         * BASE QUERY DE PIX
         * ------------------------------------------------------------- */
        $pixQuery = Transaction::query()
            ->where('user_id', $user->id);

        // filtro status
        if ($statusFilter && $statusFilter !== "all") {
            $pixQuery->where(function ($q) use ($statusFilter) {
                if ($statusFilter === "EFETIVADO") {
                    $q->whereIn('status', ['paga', 'paid', 'approved', 'confirmed']);
                } elseif ($statusFilter === "PENDENTE") {
                    $q->whereIn('status', ['pending', 'pendente', 'processing', 'med', 'created']);
                } elseif ($statusFilter === "FALHADO") {
                    $q->whereIn('status', ['failed', 'falha', 'error', 'denied', 'canceled', 'cancelled']);
                }
            });
        }

        // filtro busca — CORRIGIDO
        if ($search !== '') {
            $pixQuery->where(function ($q) use ($search) {
                $s = "%{$search}%";

                $q->where('id', 'LIKE', $s)
                    ->orWhere('txid', 'LIKE', $s)
                    ->orWhere('e2e_id', 'LIKE', $s)
                    ->orWhere('external_reference', 'LIKE', $s)
                    ->orWhere('description', 'LIKE', $s);
            });
        }

        $pixTotal = $pixQuery->count();

        // itens paginados
        $pixRows = $pixQuery
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        /* -------------------------------------------------------------
         * NORMALIZAÇÃO PIX
         * ------------------------------------------------------------- */
        $pix = $pixRows->map(function ($t) use ($user) {

            $raw = strtolower((string) $t->status);
            $statusEnum = TransactionStatus::fromLoose($raw);

            $amount = (float) $t->amount;
            $credit = ($t->direction ?? 'in') === 'in';

            // Cálculo taxa
            $fee = (float) ($t->fee ?? 0);

            if ($fee <= 0) {
                if ($credit && $user->tax_in_enabled) {
                    $fee = $user->tax_in_mode === 'fixo'
                        ? round($user->tax_in_fixed, 2)
                        : round($amount * ($user->tax_in_percent / 100), 2);
                }

                if (!$credit && $user->tax_out_enabled) {
                    $fee = $user->tax_out_mode === 'fixo'
                        ? round($user->tax_out_fixed, 2)
                        : round($amount * ($user->tax_out_percent / 100), 2);
                }
            }

            $fee = round($fee, 2);
            $net = $credit ? ($amount - $fee) : -($amount + $fee);

            return [
                '_kind'        => 'PIX',

                'id'           => $t->id,
                'credit'       => $credit,
                'amount'       => $amount,
                'fee'          => $fee,
                'net'          => $net,

                'status'       => $statusEnum->value,
                'status_label' => $statusEnum->label(),

                'description'  => $t->description ?? '',
                'txid'         => $t->txid ?? '',
                'e2e'          => $t->e2e_id ?? '',
                'external_reference' => $t->external_reference ?? '',

                'createdAt'    => optional($t->created_at)->toIso8601String(),
                'paidAt'       => optional($t->paid_at)->toIso8601String(),

                'metadata'     => $t->provider_payload ?? [],
                'provider'     => $t->provider,
            ];
        });

        /* -------------------------------------------------------------
         * SAQUES — mesmo filtro e busca
         * ------------------------------------------------------------- */
        $wdQuery = Withdraw::query()
            ->where('user_id', $user->id);

        if ($statusFilter && $statusFilter !== "all") {
            $wdQuery->where(function ($q) use ($statusFilter) {
                if ($statusFilter === "EFETIVADO") {
                    $q->whereIn('status', ['paid', 'completed', 'approved', 'confirmed']);
                } elseif ($statusFilter === "PENDENTE") {
                    $q->whereIn('status', ['pending', 'processing', 'created', 'authorized']);
                } elseif ($statusFilter === "FALHADO") {
                    $q->whereIn('status', ['failed', 'error', 'denied', 'canceled', 'cancelled']);
                }
            });
        }

        if ($search !== '') {
            $wdQuery->where(function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where('id', 'LIKE', $s)
                    ->orWhere('pixkey', 'LIKE', $s)
                    ->orWhere('description', 'LIKE', $s)
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.endtoend') LIKE ?", [$s]);
            });
        }

        $wdTotal = $wdQuery->count();

        $wdRows = $wdQuery
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $statusMapWd = [
            'paid'      => 'paga',
            'completed' => 'paga',
            'approved'  => 'paga',
            'confirmed' => 'paga',

            'pending'     => 'pendente',
            'processing'  => 'pendente',
            'created'     => 'pendente',
            'authorized'  => 'pendente',

            'failed'    => 'falha',
            'error'     => 'falha',
            'denied'    => 'falha',
            'canceled'  => 'falha',
            'cancelled' => 'falha',
        ];

        /* -------------------------------------------------------------
         * NORMALIZAÇÃO SAQUE
         * ------------------------------------------------------------- */
        $withdraws = $wdRows->map(function ($w) use ($statusMapWd) {

            $raw = strtolower($w->status);
            $status = $statusMapWd[$raw] ?? 'pendente';

            $liq  = (float) $w->amount;
            $gross = $w->gross_amount !== null ? (float) $w->gross_amount : $liq;

            $fee = $w->fee_amount !== null
                ? (float) $w->fee_amount
                : max(0, $gross - $liq);

            return [
                '_kind'        => 'SAQUE',

                'id'           => $w->id,
                'credit'       => false,
                'amount'       => $gross,
                'fee'          => round($fee, 2),
                'net'          => -$gross,

                'status'       => $status,
                'status_label' => ucfirst($status),

                'description' => $w->description ?? 'Saque',

                'createdAt'   => optional($w->created_at)->toIso8601String(),
                'paidAt'      => optional($w->processed_at)->toIso8601String(),

                'metadata' => [
                    'pixkey'      => $w->pixkey,
                    'pixkey_type' => $w->pixkey_type,
                    'provider'    => $w->provider,
                ],
            ];
        });

        /* -------------------------------------------------------------
         * MERGE FINAL
         * ------------------------------------------------------------- */
        $all = $pix
            ->concat($withdraws)
            ->sortByDesc(fn ($r) => $r['paidAt'] ?: $r['createdAt'])
            ->values();

        return response()->json([
            'success'      => true,
            'page'         => $page,
            'perPage'      => $perPage,
            'total'        => $pixTotal + $wdTotal,
            'transactions' => $all,
        ]);
    }
}
