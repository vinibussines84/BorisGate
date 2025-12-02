<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Carbon\Carbon;

class TaxCheckerController extends Controller
{
    /**
     * Página principal do validador de taxas.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 🔒 Permissão restrita: apenas dashrash == 1
        if ((int) ($user->dashrash ?? 0) !== 1) {
            abort(403, 'Acesso negado: sua conta não possui permissão.');
        }

        // 🔐 Gate adicional (segurança extra)
        if (Gate::denies('view-taxes')) {
            abort(403, 'Acesso não autorizado.');
        }

        // 🕒 Intervalo de datas (padrão: hoje)
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::today()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::today()->endOfDay();

        // 👤 Filtro por usuário (opcional)
        $userId = $request->input('user_id');

        // 🔍 Buscar apenas transações PAGAS dentro do período
        $query = Transaction::query()
            ->cashIn()
            ->where('status', 'paga')
            ->whereBetween('created_at', [$start, $end])
            ->with('user');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // 🔢 Paginação
        $transactions = $query
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // 💰 Calcula campos auxiliares (líquido, cliente, lucro unitário)
        $transactions->getCollection()->transform(function ($t) {
            $t->expected_liquid = $this->calcLiquidante($t->amount);
            $t->expected_client = $this->calcCliente($t->amount);
            $t->expected_profit = $t->expected_liquid - $t->expected_client; // lucro unitário
            return $t;
        });

        // 📊 Estatísticas agregadas (só pagas)
        $stats = $this->getStats($start, $end, $userId);

        // 👥 Lista de usuários para o select
        $users = User::select('id', 'email', 'nome_completo')
            ->orderBy('nome_completo')
            ->get();

        // Retorna para o Inertia
        return Inertia::render('TaxChecker', [
            'transactions' => $transactions,
            'stats' => $stats,
            'users' => $users,
            'selected_user_id' => $userId,
            'date_range' => [
                'start' => $start->toDateTimeString(),
                'end'   => $end->toDateTimeString(),
            ],
        ]);
    }

    /**
     * 📈 Estatísticas consolidadas (apenas transações pagas)
     */
    private function getStats(Carbon $start, Carbon $end, ?int $userId = null): array
    {
        // Transações pagas (entradas)
        $txBase = Transaction::query()
            ->cashIn()
            ->where('status', 'paga')
            ->whereBetween('created_at', [$start, $end]);

        // Saques pagos
        $wdBase = Withdraw::query()
            ->where('status', 'pago')
            ->whereBetween('created_at', [$start, $end]);

        if ($userId) {
            $txBase->where('user_id', $userId);
            $wdBase->where('user_id', $userId);
        }

        // 📊 Dados brutos
        $paidOrdersCount = (clone $txBase)->count();
        $totalBruto = (clone $txBase)->sum('amount');
        $transactionCount = $paidOrdersCount;

        $withdrawCount = (clone $wdBase)->count();
        $withdrawTotal = (clone $wdBase)->sum('gross_amount');

        // 🧮 Cálculos de taxas
        $taxaLiquidanteEntradas = ($totalBruto * 0.015) + ($transactionCount * 0.10); // 1.5% + 0.10
        $valorLiquidoLiquidante = $totalBruto - $taxaLiquidanteEntradas;

        $taxaIntermediario = $totalBruto * 0.04; // 4% cobrado do cliente
        $valorLiquidoCliente = $totalBruto - $taxaIntermediario;

        // 🧾 Bruto Interno (lucro total = diferença entre os dois líquidos)
        $brutoInterno = $valorLiquidoLiquidante - $valorLiquidoCliente;

        // 🏦 Taxa de saque (R$ 0,10 por saque pago)
        $taxaLiquidanteSaques = $withdrawCount * 0.10;

        return [
            'paid_orders_count'        => $paidOrdersCount,
            'withdraw_count'           => $withdrawCount,
            'withdraw_total'           => round($withdrawTotal, 2),
            'total_bruto'              => round($totalBruto, 2),
            'valor_liquido_liquidante' => round($valorLiquidoLiquidante, 2),
            'valor_liquido_cliente'    => round($valorLiquidoCliente, 2),
            'bruto_interno'            => round($brutoInterno, 2),
            'taxa_liquidante_saques'   => round($taxaLiquidanteSaques, 2),
        ];
    }

    /**
     * 💳 Calcula líquido da liquidante (bruto - 1.5% - R$0,10)
     */
    private function calcLiquidante(float $amount): float
    {
        $taxPerc = 1.5;
        $taxFixed = 0.10;
        return round($amount - ($amount * $taxPerc / 100) - $taxFixed, 2);
    }

    /**
     * 💸 Calcula o valor entregue ao cliente (bruto - 4%)
     */
    private function calcCliente(float $amount): float
    {
        $tax = 4.0;
        return round($amount - ($amount * $tax / 100), 2);
    }
}
