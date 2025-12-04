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
     * GET /api/metrics/day
     * Retorna SOMENTE métricas do DIA ATUAL.
     */
    public function day(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $tz = 'America/Sao_Paulo';

        // 🔹 Dia atual em UTC
        $dayStart = now($tz)->startOfDay()->utc();
        $dayEnd   = now($tz)->endOfDay()->utc();

        $periodo = now($tz)->locale('pt_BR')->translatedFormat('d \\d\\e F, Y');

        /*
        |--------------------------------------------------------------------------
        | PIX PAGAS (DIA)
        |--------------------------------------------------------------------------
        */

        $qtdPagasDia = Transaction::where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$dayStart, $dayEnd])
            ->count();

        $valorBrutoDia = (float) Transaction::where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$dayStart, $dayEnd])
            ->sum('amount');

        // 🔹 Taxas aplicadas corretamente
        $valorLiquidoDia = $valorBrutoDia;

        if ($u->tax_in_enabled) {
            $percent = max(0, (float) ($u->tax_in_percent ?? 0));
            $fixed   = max(0, (float) ($u->tax_in_fixed ?? 0));

            if ($percent > 0) {
                $valorLiquidoDia -= ($valorBrutoDia * ($percent / 100));
            }

            // IMPORTANTE: taxa fixa aplicada por transação
            if ($fixed > 0 && $qtdPagasDia > 0) {
                $valorLiquidoDia -= ($fixed * $qtdPagasDia);
            }

            if ($valorLiquidoDia < 0) {
                $valorLiquidoDia = 0;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SAQUES DO DIA
        |--------------------------------------------------------------------------
        */

        $saidasLiquidoDia = (float) Withdraw::where('user_id', $u->id)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$dayStart, $dayEnd])
            ->sum('amount');

        $qtdSaquesDia = Withdraw::where('user_id', $u->id)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$dayStart, $dayEnd])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | VOLUME TOTAL PIX DO DIA (BRUTO)
        |--------------------------------------------------------------------------
        */

        $volumePixDia = (float) Transaction::where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$dayStart, $dayEnd])
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'periodo'           => $periodo,
                'qtdPagasDia'       => $qtdPagasDia,
                'valorBrutoDia'     => $valorBrutoDia,
                'valorLiquidoDia'   => $valorLiquidoDia,
                'volumePixDia'      => $volumePixDia,
                'qtdSaquesDia'      => $qtdSaquesDia,
                'saidasLiquidoDia'  => $saidasLiquidoDia,
            ],
        ]);
    }

    /**
     * GET /api/metrics/month
     * Mantém métricas mensais completas. (Permanece igual)
     */
    public function month(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $tz = 'America/Sao_Paulo';
        $startTz = Carbon::now($tz)->startOfMonth()->startOfDay();
        $endTz   = Carbon::now($tz)->endOfDay();

        $startUtc = $startTz->clone()->utc();
        $endUtc   = $endTz->clone()->utc();

        $periodo = sprintf(
            '%s – %s',
            $startTz->locale('pt_BR')->translatedFormat('d \\d\\e F'),
            now($tz)->locale('pt_BR')->translatedFormat('d \\d\\e F, Y')
        );

        // Entradas (Pix pagas)
        $entradasBruto = (float) Transaction::where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->sum('amount');

        // Taxas
        $entradasLiquido = $entradasBruto;

        if ($u->tax_in_enabled) {
            $percent = max(0, (float) ($u->tax_in_percent ?? 0));
            $fixed   = max(0, (float) ($u->tax_in_fixed ?? 0));

            if ($percent > 0) {
                $entradasLiquido -= ($entradasBruto * ($percent / 100));
            }

            // taxa fixa no mês inteiro (por transação — não podemos calcular sem contar)
            $qtdTransacoesMes = Transaction::where('user_id', $u->id)
                ->where('direction', Transaction::DIR_IN)
                ->where('method', 'pix')
                ->where('status', TransactionStatus::PAID)
                ->whereBetween('paid_at', [$startUtc, $endUtc])
                ->count();

            if ($fixed > 0) {
                $entradasLiquido -= ($fixed * $qtdTransacoesMes);
            }

            if ($entradasLiquido < 0) {
                $entradasLiquido = 0;
            }
        }

        // Volume bruto
        $volumePix = $entradasBruto;

        // Saques pagos no mês
        $saidasMes = (float) Withdraw::where('user_id', $u->id)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$startUtc, $endUtc])
            ->sum('amount');

        // Pendentes no mês
        $pendentes = (int) Transaction::where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PENDING)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'entradaMes'     => $entradasLiquido,
                'entradaBruto'   => $entradasBruto,
                'saidaMes'       => $saidasMes,
                'pendentes'      => $pendentes,
                'volumePix'      => $volumePix,
                'periodo'        => $periodo,
            ],
        ]);
    }

    /**
     * PUT /api/metrics/goal
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
     */
    public function paidFeed(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $limit = (int) max(1, min((int) $request->integer('limit', 30), 100));

        // PIX pagas
        $pix = Transaction::where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAID)
            ->orderByDesc('paid_at')
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

        // Saques pagos
        $saques = Withdraw::where('user_id', $u->id)
            ->where('status', 'paid')
            ->orderByDesc('processed_at')
            ->limit($limit)
            ->get()
            ->map(fn ($w) => [
                'kind'        => 'SAQUE',
                'id'          => (int) $w->id,
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

        // Mescla e ordena
        $merged = collect($pix)
            ->merge($saques)
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
