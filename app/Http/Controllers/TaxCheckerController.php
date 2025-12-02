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
     * 🧾 Exibe a página do Validador de Taxas
     * ---------------------------------------------------------------
     * Mostra transações do período selecionado (por padrão, o dia atual)
     * e calcula:
     *  - Total bruto recebido
     *  - Valor líquido após taxa da liquidante
     *  - Lucro final
     *  - Saques pagos e suas taxas fixas
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 🔐 Permissão: apenas dashrash == 1
        if ((int) ($user->dashrash ?? 0) !== 1) {
            abort(403, 'Acesso negado: sua conta não possui permissão.');
        }

        // 🔒 Gate adicional (segurança)
        if (Gate::denies('view-taxes')) {
            abort(403, 'Acesso não autorizado.');
        }

        // 📆 Intervalo de datas (padrão: dia atual)
        $start = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::today()->startOfDay();

        $end = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::today()->endOfDay();

        // 🔍 Filtro opcional por usuário
        $userId = $request->input('user_id');

        // 📦 Transações de entrada (CashIn) no período
        $query = Transaction::query()
            ->cashIn()
            ->whereBetween('created_at', [$start, $end])
            ->with('user');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        // 🔢 Paginação (30 por página)
        $transactions = $query
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // 💰 Calcula líquido, cliente e lucro por transação
        $transactions->getCollection()->transform(function ($t) {
            $t->expected_liquid = $this->calcLiquidante($t->amount);
            $t->expected_client = $this->calcCliente($t->amount);
            $t->expected_profit = $t->expected_liquid - $t->expected_client;
            return $t;
        });

        // 📊 Estatísticas gerais do período
        $stats = $this->getStats($start, $end, $userId);

        // 👥 Lista de usuários para o filtro
        $users = User::select('id', 'email', 'nome_completo')
            ->orderBy('nome_completo')
            ->get();

        // 🔙 Retorna para a view Inertia
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
     * 🧮 Simulação de taxas e lucro (AJAX)
     */
    public function simulate(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = (float) $request->amount;
        $liquid = $this->calcLiquidante($amount);
        $client = $this->calcCliente($amount);
        $profit = $liquid - $client;

        return response()->json([
            'bruto' => $amount,
            'liquido_liquidante' => $liquid,
            'liquido_cliente' => $client,
            'lucro' => $profit,
        ]);
    }

    /**
     * 📊 Estatísticas gerais do período
     * ---------------------------------------------------------------
     * Calcula:
     *  - total bruto
     *  - total líquido (pós-liquidante)
     *  - lucro final
     *  - saques pagos + taxas fixas
     */
    private function getStats(Carbon $start, Carbon $end, ?int $userId = null): array
    {
        // 🔍 Transações do período
        $txBase = Transaction::query()
            ->cashIn()
            ->whereBetween('created_at', [$start, $end]);

        // 🔍 Saques pagos
        $wdBase = Withdraw::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'pago');

        if ($userId) {
            $txBase->where('user_id', $userId);
            $wdBase->where('user_id', $userId);
        }

        // ✅ Contagem de pedidos pagos
        $paidOrdersCount = (clone $txBase)->where('status', 'paga')->count();

        // 💵 Total bruto recebido
        $totalBruto = (clone $txBase)->sum('amount');

        // 💰 Quantidade de transações (para R$0,10 por entrada)
        $transactionCount = (clone $txBase)->count();

        // 💸 Saques pagos
        $withdrawCount = (clone $wdBase)->count();
        $withdrawTotal = (clone $wdBase)->sum('gross_amount');

        // 📉 Taxas da liquidante sobre entradas
        $taxaLiquidanteEntradas = ($totalBruto * 0.015) + ($transactionCount * 0.10);

        // 📉 Taxas fixas da liquidante sobre saques pagos (R$0.10 por saque)
        $taxaLiquidanteSaques = $withdrawCount * 0.10;

        // 🏦 Valor líquido recebido da liquidante (entradas - taxas)
        $valorLiquidoLiquidante = round($totalBruto - $taxaLiquidanteEntradas, 2);

        // 💸 Lucro final do período
        // = líquido da liquidante - (2.5% de taxa intermediária sobre o bruto)
        $taxaIntermediario = $totalBruto * 0.025;
        $lucro = round($valorLiquidoLiquidante - $taxaIntermediario, 2);

        return [
            'paid_orders_count'        => $paidOrdersCount,
            'withdraw_count'           => $withdrawCount,
            'withdraw_total'           => round($withdrawTotal, 2),
            'total_bruto'              => round($totalBruto, 2),
            'valor_liquido_liquidante' => $valorLiquidoLiquidante,
            'lucro'                    => $lucro,
            'taxa_liquidante_saques'   => round($taxaLiquidanteSaques, 2),
        ];
    }

    /**
     * 💳 Calcula o valor líquido recebido da liquidante
     * (Bruto - 1.5% - R$0.10 fixo)
     */
    private function calcLiquidante(float $amount): float
    {
        $taxPerc = 1.5;
        $taxFixed = 0.10;
        return round($amount - ($amount * $taxPerc / 100) - $taxFixed, 2);
    }

    /**
     * 💸 Calcula o valor entregue ao cliente
     * (Bruto - 4% cobrados do cliente)
     */
    private function calcCliente(float $amount): float
    {
        $tax = 4.0;
        return round($amount - ($amount * $tax / 100), 2);
    }
}
