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
     * Retorna métricas do dia (00h às 23h59) e volume mensal total.
     */
    public function day(Request $request)
    {
        $u = $request->user();
        if (!$u) {
            return response()->json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
        }

        $tz = 'America/Sao_Paulo';
        $dayStart = Carbon::now($tz)->startOfDay();
        $dayEnd   = Carbon::now($tz)->endOfDay();

        $startUtc = $dayStart->clone()->utc();
        $endUtc   = $dayEnd->clone()->utc();

        $periodo = $dayStart->locale('pt_BR')->translatedFormat('d \\d\\e F, Y');

        // 🔹 Quantidade de transações pagas no dia
        $qtdPagasDia = Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->count();

        // 🔹 Valor bruto pago no dia
        $valorBrutoDia = (float) Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->sum('amount');

        // 🔹 Valor líquido (aplicando taxa de cash-in)
        $valorLiquidoDia = $valorBrutoDia;
        if ($u->tax_in_enabled) {
            $percent = max(0, (float) ($u->tax_in_percent ?? 0));
            $fixed   = max(0, (float) ($u->tax_in_fixed ?? 0));

            if ($percent > 0) {
                $valorLiquidoDia -= ($valorBrutoDia * ($percent / 100));
            }
            if ($fixed > 0) {
                $valorLiquidoDia -= $fixed;
            }
            if ($valorLiquidoDia < 0) {
                $valorLiquidoDia = 0;
            }
        }

        // 🔹 Volume total Pix do mês
        $mesInicio = Carbon::now($tz)->startOfMonth()->startOfDay()->utc();
        $mesFim    = Carbon::now($tz)->endOfDay()->utc();

        $volumePixMes = (float) Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$mesInicio, $mesFim])
            ->sum('amount');

        // 🔹 Saídas (saques pagos no dia)
        $saidasDia = (float) Withdraw::query()
            ->where('user_id', $u->id)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$startUtc, $endUtc])
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'qtdPagasDia'     => $qtdPagasDia,
                'valorBrutoDia'   => $valorBrutoDia,
                'valorLiquidoDia' => $valorLiquidoDia,
                'saidasDia'       => $saidasDia,
                'volumePixMes'    => $volumePixMes,
                'periodo'         => $periodo,
            ],
        ]);
    }

    /**
     * GET /api/metrics/month
     * Mantém métricas mensais completas (entrada, saída, pendentes, etc.).
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
        $entradasBruto = (float) Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // Aplica taxa
        $entradasLiquido = $entradasBruto;
        if ($u->tax_in_enabled) {
            $percent = max(0, (float) ($u->tax_in_percent ?? 0));
            $fixed   = max(0, (float) ($u->tax_in_fixed ?? 0));

            if ($percent > 0) {
                $entradasLiquido -= ($entradasBruto * ($percent / 100));
            }
            if ($fixed > 0) {
                $entradasLiquido -= $fixed;
            }
            if ($entradasLiquido < 0) {
                $entradasLiquido = 0;
            }
        }

        // Volume total Pix
        $volumePix = (float) Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // Saídas (saques pagos no mês)
        $saidasMes = (float) Withdraw::query()
            ->where('user_id', $u->id)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->sum('amount');

        // Pendentes
        $pendentes = (int) Transaction::query()
            ->where('user_id', $u->id)
            ->where('direction', Transaction::DIR_IN)
            ->where('method', 'pix')
            ->where('status', TransactionStatus::PENDENTE)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        // Chargebacks (se existir)
        $statusChargeback = defined(TransactionStatus::class . '::CHARGEBACK')
            ? TransactionStatus::CHARGEBACK
            : 'chargeback';

        $chargebacksMes = (int) Transaction::query()
            ->where('user_id', $u->id)
            ->where('method', 'pix')
            ->where('status', $statusChargeback)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'entradaMes'      => $entradasLiquido,
                'entradaBruto'    => $entradasBruto,
                'saidaMes'        => $saidasMes,
                'pendentes'       => $pendentes,
                'volumePix'       => $volumePix,
                'chargebacksMes'  => $chargebacksMes,
                'periodo'         => $periodo,
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

        // Saques pagos
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
