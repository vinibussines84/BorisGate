<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdraw; // ✅ importar
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardIndicatorsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 📅 Mês atual
        $from = Carbon::now()->startOfMonth()->toDateString();
        $to   = Carbon::now()->endOfMonth()->toDateString();

        // 🔎 Base para TRANSACOES (entradas/pendentes/conversão)
        $q = Transaction::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        // ✅ Entradas (direction=in & status=paga)
        $entradaMes = (clone $q)
            ->where('direction', 'in')
            ->where('status', TransactionStatus::PAGA->value)
            ->sum('amount');

        // ✅ Saída do mês via WITHDRAW do usuário (status = paid)
        //    amount = LÍQUIDO; se quiser bruto/taxa, veja abaixo.
        $wq = Withdraw::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $saidaMes = (clone $wq)
            ->where('status', 'paid')      // considere 'processing' se quiser incluir em trânsito
            ->sum('amount');               // líquido enviado ao usuário

        // ▶️ Caso queira expor também bruto e taxas (opcional):
        // $saidaMesBruto = (clone $wq)->where('status', 'paid')->sum('gross_amount');
        // $taxasSaqueMes = (clone $wq)->where('status', 'paid')->sum('fee_amount');

        // ✅ Pendentes (em transações)
        $pendentes = (clone $q)
            ->where('status', TransactionStatus::PENDENTE->value)
            ->count();

        // ✅ Conversão (aprovadas / total) — em transações
        $totalMes = (clone $q)->count();
        $aprovadasMes = (clone $q)
            ->where('status', TransactionStatus::PAGA->value)
            ->count();

        $conversaoMes = $totalMes > 0
            ? round(($aprovadasMes / $totalMes) * 100, 1)
            : 0;

        return response()->json([
            'success'       => true,
            'periodo'       => 'Este mês',
            'entradaMes'    => round($entradaMes, 2),
            'saidaMes'      => round($saidaMes, 2),
            // 'saidaMesBruto'  => round($saidaMesBruto ?? 0, 2), // opcional
            // 'taxasSaqueMes'  => round($taxasSaqueMes ?? 0, 2), // opcional
            'pendentes'     => $pendentes,
            'aprovadasMes'  => $aprovadasMes,
            'totalMes'      => $totalMes,
            'conversaoMes'  => $conversaoMes,
            'metaMax'       => 100000,
            'range' => [
                'from' => $from,
                'to'   => $to,
            ],
        ]);
    }
}
