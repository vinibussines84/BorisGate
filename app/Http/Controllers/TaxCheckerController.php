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

        // 🕒 Intervalo de datas (padrão: dia atual)
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::today()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::today()->endOfDay();

        // 👤 Filtro por usuário (se enviado)
        $userId = $request->input('user_id');

        // 🔍 Transações filtradas por data e usuário
        $query = Transaction::query()
            ->cashIn()
            ->whereBetween('created_at', [$start, $end])
            ->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $userId);
        }

        $transactions = $query
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // 💰 Cálculo de lucro individual
        $transactions->getCollection()->transform(function ($t) {
            $t->expected_liquid = $this->calcLiquidante($t->amount);
            $t->expected_client = $this->calcCliente($t->amount);
            $t->expected_profit = $t->expected_liquid - $t->expected_client;
            return $t;
        });

        // 📊 Estatísticas gerais filtradas
        $stats = $this->getStats($start, $end, $userId);

        // 👥 Lista de usuários (para o select)
        $users = User::select('id', 'email', 'nome_completo')->orderBy('nome_completo')->get();

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
     * 📈 Estatísticas consolidadas (respeitando user_id)
     */
    private function getStats(Carbon $start, Carbon $end, ?int $userId = null): array
    {
        // Transações filtradas
        $txBase = Transaction::query()
            ->cashIn()
            ->whereBetween('created_at', [$start, $end]);

        // Saques pagos filtrados
        $wdBase = Withdraw::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'pago');

        if ($userId) {
            $txBase->where('user_id', $userId);
            $wdBase->where('user_id', $userId);
        }

        // Pedidos pagos (status exato)
        $paidOrdersCount = (clone $txBase)->where('status', 'paga')->count();

        // Entradas (cash-in)
        $totalBruto = (clone $txBase)->sum('amount');
        $transactionCount = (clone $txBase)->count();

        // Saques pagos
        $withdrawCount = (clone $wdBase)->count();
        $withdrawTotal = (clone $wdBase)->sum('gross_amount');

        // 🧾 Taxas e lucro
        $taxaLiquidanteEntradas = ($totalBruto * 0.015) + ($transactionCount * 0.10);
        $taxaLiquidanteSaques = $withdrawCount * 0.10;

        $valorLiquidoLiquidante = round($totalBruto - $taxaLiquidanteEntradas, 2);
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
