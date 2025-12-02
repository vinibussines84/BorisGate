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
     * 🧾 Página principal do validador de taxas
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 🔒 Autorização
        if (Gate::denies('view-taxes')) {
            abort(403, 'Acesso não autorizado.');
        }

        // 📅 Intervalo de datas — padrão: hoje
        $startOfDay = Carbon::parse($request->input('start_date', Carbon::today()->startOfDay()));
        $endOfDay   = Carbon::parse($request->input('end_date', Carbon::today()->endOfDay()));

        // 🔍 Filtro por usuário
        $userId = $request->integer('user_id');

        // 📄 Paginação
        $perPage = $request->integer('per_page', 50);

        // 📦 Transações filtradas
        $query = Transaction::query()
            ->cashIn()
            ->whereBetween('created_at', [$startOfDay, $endOfDay]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $transactions = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        // 💰 Cálculos de taxa/lucro
        $transactions->getCollection()->transform(function ($t) {
            $t->expected_liquid = $this->calcLiquidante($t->amount);
            $t->expected_client = $this->calcCliente($t->amount);
            $t->expected_profit = $t->expected_liquid - $t->expected_client;
            return $t;
        });

        // 📊 Estatísticas agregadas
        $stats = $this->getStats($startOfDay, $endOfDay, $userId);

        // 👤 Lista de usuários disponíveis para filtro
        $users = User::select('id', 'nome_completo as name', 'email')
            ->orderBy('nome_completo')
            ->get();

        // 📤 Retorno para Inertia
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
     * 🧮 Simulação de taxas (AJAX / API)
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
     * 📈 Estatísticas agregadas (por data e usuário)
     */
    private function getStats(Carbon $start, Carbon $end, ?int $userId = null): array
    {
        $txBase = Transaction::query()->cashIn();
        $wdBase = Withdraw::query();

        if ($userId) {
            $txBase->where('user_id', $userId);
            $wdBase->where('user_id', $userId);
        }

        $txBase->whereBetween('created_at', [$start, $end]);
        $wdBase->whereBetween('created_at', [$start, $end]);

        // ✅ Usa scopes nativos do modelo Transaction
        $paidOrdersCount = (clone $txBase)->paga()->count();
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
        $taxPerc  = 1.5;   // 1.5%
        $taxFixed = 0.10;  // R$0,10 fixo
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
