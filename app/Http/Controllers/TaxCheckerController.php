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
     * Exibe a página do validador de taxas (apenas transações do dia atual).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 🔒 Gate adicional (ex: apenas usuários autorizados)
        if (Gate::denies('view-taxes')) {
            abort(403, 'Acesso não autorizado.');
        }

        // 🕒 Período do dia atual: 00:00 → 23:59
        $startOfDay = Carbon::today()->startOfDay();
        $endOfDay   = Carbon::today()->endOfDay();

        // 🔍 Filtro opcional por usuário
        $userId = $request->input('user_id');

        // 📦 Buscar transações do dia
        $query = Transaction::query()
            ->cashIn()
            ->whereBetween('created_at', [$startOfDay, $endOfDay]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $transactions = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // 💰 Calcular lucro esperado
        $transactions->getCollection()->transform(function ($t) {
            $t->expected_liquid = $this->calcLiquidante($t->amount);
            $t->expected_client = $this->calcCliente($t->amount);
            $t->expected_profit = $t->expected_liquid - $t->expected_client;
            return $t;
        });

        // 📊 Estatísticas gerais do dia
        $stats = $this->getDailyStats($startOfDay, $endOfDay, $userId);

        // 👤 Lista de usuários (para filtro)
        $users = User::select('id', 'nome_completo as name', 'email')
            ->orderBy('nome_completo')
            ->get();

        // Retorno Inertia
        return Inertia::render('TaxChecker', [
            'transactions'      => $transactions,
            'stats'             => $stats,
            'users'             => $users,
            'selected_user_id'  => $userId,
            'date_range'        => [
                'start' => $startOfDay->toDateTimeString(),
                'end'   => $endOfDay->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Endpoint AJAX/API para simular cálculo de taxas.
     */
    public function simulate(Request $request)
    {
        if (Gate::denies('view-taxes')) {
            abort(403, 'Acesso não autorizado.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = $validated['amount'];
        $liquid = $this->calcLiquidante($amount);
        $client = $this->calcCliente($amount);
        $profit = $liquid - $client;

        return response()->json([
            'bruto'               => $amount,
            'liquido_liquidante'  => $liquid,
            'liquido_cliente'     => $client,
            'lucro'               => $profit,
        ]);
    }

    /**
     * 📈 Estatísticas do dia (00h–23h59)
     */
    private function getDailyStats(Carbon $start, Carbon $end, ?int $userId = null): array
    {
        $txBase = Transaction::query()
            ->whereBetween('created_at', [$start, $end])
            ->cashIn();

        $wdBase = Withdraw::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($userId) {
            $txBase->where('user_id', $userId);
            $wdBase->where('user_id', $userId);
        }

        $paidOrdersCount = (clone $txBase)->where('status', 'paid')->count();
        $withdrawCount   = (clone $wdBase)->count();
        $withdrawTotal   = (clone $wdBase)->sum('gross_amount');

        return [
            'paid_orders_count' => $paidOrdersCount,
            'withdraw_count'    => $withdrawCount,
            'withdraw_total'    => round($withdrawTotal, 2),
        ];
    }

    /**
     * 💰 Calcula o líquido recebido da liquidante.
     */
    private function calcLiquidante(float $amount): float
    {
        $taxPerc  = 1.5;  // 1.5%
        $taxFixed = 0.10; // R$0,10 fixo
        return round($amount - ($amount * $taxPerc / 100) - $taxFixed, 2);
    }

    /**
     * 💸 Calcula o líquido entregue ao cliente (sua taxa de 4%).
     */
    private function calcCliente(float $amount): float
    {
        $tax = 4.0; // 4%
        return round($amount - ($amount * $tax / 100), 2);
    }
}
