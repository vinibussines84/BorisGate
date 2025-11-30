<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MetricsController extends Controller
{
    /**
     * GET /api/metrics/month
     * Retorna métricas do mês corrente para o usuário autenticado.
     */
    public function month(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $tz = 'America/Sao_Paulo';
        $startTz  = Carbon::now($tz)->startOfMonth();
        $endTz    = Carbon::now($tz)->endOfDay();
        $startUtc = $startTz->clone()->utc();
        $endUtc   = $endTz->clone()->utc();

        // 🔹 Entradas (Pix pagas)
        $entradasMes = Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // 🔹 Volume total (todas Pix criadas)
        $volumePix = Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // 🔹 Saídas (saques pagos)
        $saidasMes = Withdraw::query()
            ->where('user_id', $u->id)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // 🔹 Pendentes
        $pendentes = Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PENDENTE)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        // 🔹 Chargebacks (fallback)
        $statusChargeback = defined(TransactionStatus::class . '::CHARGEBACK')
            ? TransactionStatus::CHARGEBACK
            : 'chargeback';

        $chargebacksMes = Transaction::query()
            ->where('user_id', $u->id)
            ->where('method', 'pix')
            ->where('status', $statusChargeback)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        // 🔹 Período formatado
        $periodo = sprintf(
            '%s – %s',
            $startTz->locale('pt_BR')->translatedFormat('d \\d\\e F'),
            now($tz)->locale('pt_BR')->translatedFormat('d \\d\\e F, Y')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'entradaMes'     => (float) $entradasMes,
                'saidaMes'       => (float) $saidasMes,
                'pendentes'      => (int) $pendentes,
                'volumePix'      => (float) $volumePix,
                'chargebacksMes' => (int) $chargebacksMes,
                'periodo'        => $periodo,
            ],
        ]);
    }

    /**
     * PUT /api/metrics/goal
     * Atualiza meta mensal (se usada no painel)
     */
    public function updateGoal(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $validated = $request->validate([
            'monthly_goal' => ['required', 'numeric', 'min:1', 'max:9999999999'],
        ]);

        $u->monthly_goal = $validated['monthly_goal'];
        $u->save();

        return response()->json([
            'success' => true,
            'data'    => ['monthly_goal' => (float) $u->monthly_goal],
            'message' => 'Meta atualizada.',
        ]);
    }

    /**
     * GET /api/metrics/paid-feed
     * Lista unificada das últimas transações pagas (PIX e SAQUES)
     */
    public function paidFeed(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $limit = (int) max(1, min((int) $request->integer('limit', 30), 100));

        // 🔹 PIX pagas
        $pix = Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($tx) => [
                'kind'        => 'PIX',
                'id'          => (int) $tx->id,
                'e2e'         => $tx->e2e_id ?? data_get($tx->provider_payload, 'e2e_id'),
                'amount'      => (float) $tx->amount,
                'fee'         => (float) ($tx->fee ?? 0),
                'net'         => (float) ($tx->net_amount ?? ($tx->amount - ($tx->fee ?? 0))),
                'status'      => 'paga',
                'statusLabel' => 'Paga',
                'createdAt'   => optional($tx->created_at)->toIso8601String(),
                'paidAt'      => optional($tx->paid_at)->toIso8601String(),
                'credit'      => true,
            ]);

        // 🔹 SAQUES pagos
        $saques = Withdraw::query()
            ->where('user_id', $u->id)
            ->where('status', 'paid')
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($w) => [
                'kind'        => 'SAQUE',
                'id'          => (int) $w->id,
                // ✅ Inclui o E2E salvo no meta JSON
                'e2e'         => data_get($w->meta, 'e2e') ?? data_get($w->meta, 'endtoend'),
                'amount'      => (float) ($w->gross_amount ?? $w->amount),
                'fee'         => (float) ($w->fee_amount ?? 0),
                'net'         => (float) ($w->amount ?? 0),
                'status'      => 'paga',
                'statusLabel' => 'Paga',
                'createdAt'   => optional($w->created_at)->toIso8601String(),
                'paidAt'      => optional($w->processed_at)->toIso8601String(),
                'credit'      => false,
            ]);

        // 🔹 Unifica e ordena
        $merged = $pix->merge($saques)
            ->sortByDesc(fn ($i) => $i['paidAt'] ?? $i['createdAt'])
            ->values()
            ->take($limit)
            ->all();

        return response()->json([
            'success' => true,
            'data'    => $merged,
        ]);
    }
}
