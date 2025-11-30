<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Enums\TransactionStatus;

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

        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = min(100, (int) $request->input('perPage', 10));
        $offset   = ($page - 1) * $perPage;
        $statusFilter = $request->input('status');
        $search       = trim($request->input('search', ''));

        /* -------------------------------------------------------------
         * PIX-IN
         * ------------------------------------------------------------- */
        $pixQuery = Transaction::query()->where('user_id', $user->id);

        if ($statusFilter && $statusFilter !== "all") {
            $pixQuery->where(function ($q) use ($statusFilter) {
                if ($statusFilter === "EFETIVADO") {
                    $q->whereIn('status', ['paga','paid','approved','confirmed']);
                } elseif ($statusFilter === "PENDENTE") {
                    $q->whereIn('status', ['pending','pendente','processing','med','created']);
                } elseif ($statusFilter === "FALHADO") {
                    $q->whereIn('status', ['failed','falha','error','denied','canceled','cancelled']);
                }
            });
        }

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
        $pixRows  = $pixQuery->orderByDesc('created_at')->offset($offset)->limit($perPage)->get();

        $pix = $pixRows->map(function ($t) use ($user) {
            $statusEnum = TransactionStatus::fromLoose($t->status);
            $amount = (float) $t->amount;
            $credit = ($t->direction ?? 'in') === 'in';
            $fee    = round((float) ($t->fee ?? 0), 2);

            return [
                '_kind'  => 'PIX',
                'id'     => $t->id,
                'credit' => $credit,
                'amount' => $amount,
                'fee'    => $fee,
                'net'    => $credit ? ($amount - $fee) : -($amount + $fee),
                'status' => $statusEnum->value,
                'status_label' => $statusEnum->label(),
                'description' => $t->description ?? '',
                'txid'   => $t->txid,
                'e2e'    => $t->e2e_id ?? '',
                'external_reference' => $t->external_reference,
                'createdAt' => optional($t->created_at)->toIso8601String(),
                'paidAt'    => optional($t->paid_at)->toIso8601String(),
                'metadata'  => $t->provider_payload ?? [],
                'provider'  => $t->provider,
            ];
        });

        /* -------------------------------------------------------------
         * SAQUES
         * ------------------------------------------------------------- */
        $wdQuery = Withdraw::query()->where('user_id', $user->id);

        if ($statusFilter && $statusFilter !== "all") {
            $wdQuery->where(function ($q) use ($statusFilter) {
                if ($statusFilter === "EFETIVADO") {
                    $q->whereIn('status', ['paid','completed','approved','confirmed']);
                } elseif ($statusFilter === "PENDENTE") {
                    $q->whereIn('status', ['pending','processing','created','authorized']);
                } elseif ($statusFilter === "FALHADO") {
                    $q->whereIn('status', ['failed','error','denied','canceled','cancelled']);
                }
            });
        }

        if ($search !== '') {
            $wdQuery->where(function ($q) use ($search) {
                $s = "%{$search}%";
                $q->where('id', 'LIKE', $s)
                    ->orWhere('pixkey', 'LIKE', $s)
                    ->orWhere('description', 'LIKE', $s)
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.e2e') LIKE ?", [$s])
                    ->orWhereRaw("JSON_EXTRACT(meta, '$.endtoend') LIKE ?", [$s]);
            });
        }

        $wdTotal = $wdQuery->count();
        $wdRows  = $wdQuery->orderByDesc('created_at')->offset($offset)->limit($perPage)->get();

        $withdraws = $wdRows->map(function ($w) {
            $meta = is_array($w->meta) ? $w->meta : json_decode($w->meta ?? '{}', true);
            $e2e  = $meta['e2e'] ?? $meta['endtoend'] ?? null;
            $gross = (float) ($w->gross_amount ?? $w->amount);
            $fee   = (float) ($w->fee_amount ?? max(0, $gross - (float) $w->amount));

            return [
                '_kind'  => 'SAQUE',
                'id'     => $w->id,
                'credit' => false,
                'amount' => $gross,
                'fee'    => round($fee, 2),
                'net'    => -$gross,
                'status' => strtolower($w->status),
                'status_label' => ucfirst(strtolower($w->status)),
                'description' => $w->description ?? 'Saque',
                'e2e' => $e2e,
                'createdAt' => optional($w->created_at)->toIso8601String(),
                'paidAt'    => optional($w->processed_at)->toIso8601String(),
            ];
        });

        $all = $pix->concat($withdraws)->sortByDesc(fn($r) => $r['paidAt'] ?: $r['createdAt'])->values();

        return response()->json([
            'success' => true,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $pixTotal + $wdTotal,
            'transactions' => $all,
        ]);
    }
}
