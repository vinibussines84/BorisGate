<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\Withdraw;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransacoesStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected ?string $heading = 'Resumo de Transações';
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tz = 'America/Sao_Paulo';

        /** 🕒 AGORA: FILTRAGEM NO MESMO FUSO QUE O paid_at É SALVO */
        $inicioHoje = Carbon::today($tz);
        $amanha     = $inicioHoje->copy()->addDay();

        $inicioSemana = Carbon::now($tz)->startOfWeek();
        $inicioMes    = Carbon::now($tz)->startOfMonth();

        $tenantId = auth()->user()?->tenant_id;

        /* ============================================================
           🔄 CASH IN PAGAS HOJE (AGORA CERTINHO)
        ============================================================ */
        $baseHojePagasIn = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioHoje, $amanha]);

        $cashInTotal = (float)(clone $baseHojePagasIn)->sum('amount');
        $cashInCount =        (clone $baseHojePagasIn)->count();

        /* Apenas visual */
        $descontoPercentual = $cashInTotal * 0.015;
        $descontoFixo = $cashInCount * 0.10;
        $cashInTotalLiquidoVisual = $cashInTotal - ($descontoPercentual + $descontoFixo);

        /* ============================================================
           🔄 CASH OUT HOJE
        ============================================================ */
        $baseHojeOut = Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$inicioHoje, $amanha]);

        $cashOutTotal = (float)(clone $baseHojeOut)->sum('amount');
        $cashOutCount =        (clone $baseHojeOut)->count();

        $entradasCriadasHoje = $cashInCount;
        $saquesCriadosHoje   = $cashOutCount;
        $totalMovimentosHoje = $entradasCriadasHoje + $saquesCriadosHoje;

        /* ============================================================
           🔥 PAGAS DO DIA (IN + OUT) — AGORA CORRETO
        ============================================================ */
        $valorTransacoesPagasDiaIn = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioHoje, $amanha])
            ->sum('amount');

        $valorTransacoesPagasDiaOut = (float)Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHoje, $amanha])
            ->sum('amount');

        $valorTransacoesPagasDiaTotal = $valorTransacoesPagasDiaIn + $valorTransacoesPagasDiaOut;

        $pagasHojeInCount = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioHoje, $amanha])
            ->count();

        $pagasHojeOutCount = Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHoje, $amanha])
            ->count();

        /* ============================================================
           PIX GERADOS HOJE
        ============================================================ */
        $pixGeradosHojeValor = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->whereBetween('created_at', [$inicioHoje, $amanha])
            ->sum('amount');

        $pixGeradosHojeCount = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->whereBetween('created_at', [$inicioHoje, $amanha])
            ->count();

        /* ============================================================
           CONVERSÃO DO DIA
        ============================================================ */
        $conversaoHojePorcentagem = $pixGeradosHojeCount > 0
            ? round(($pagasHojeInCount / $pixGeradosHojeCount) * 100, 2)
            : 0;

        /* ============================================================
           TAXAS DO DIA
        ============================================================ */
        $taxasTransacoesDiaIn = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioHoje, $amanha])
            ->sum('fee');

        $taxasTransacoesDiaOut = (float)Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHoje, $amanha])
            ->sum('fee_amount');

        $taxasDiaTotal = $taxasTransacoesDiaIn + $taxasTransacoesDiaOut;

        /* ============================================================
           PERÍODOS SEMANA / MÊS
        ============================================================ */
        $totalSemanaPagas = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioSemana, $amanha])
            ->sum('amount');

        $totalMes = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$inicioMes, $amanha])
            ->whereIn('status', [TransactionStatus::PAID, TransactionStatus::PENDING])
            ->sum('amount');

        $comissaoBrutaMes = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioMes, $amanha])
            ->sum('fee');

        /* FORMATADOR */
        $brl = fn(float $v) => 'R$ ' . number_format($v, 2, ',', '.');

        return [
            Stat::make('TRANSAÇÕES DE HOJE', '')
                ->icon('heroicon-o-currency-dollar')
                ->chart([
                    (int) round($cashInTotal),
                    (int) round($cashOutTotal)
                ])
                ->color($cashInTotal >= $cashOutTotal ? 'success' : 'danger')
                ->description(
                    "IN: {$brl($cashInTotalLiquidoVisual)} ({$entradasCriadasHoje}) | OUT: {$brl($cashOutTotal)} ({$saquesCriadosHoje})"
                ),

            Stat::make('Gerado Hoje', $brl($pixGeradosHojeValor))
                ->description("{$pixGeradosHojeCount} PIX gerados")
                ->icon('heroicon-o-bolt')
                ->color('warning'),

            Stat::make('Conversão do Dia', "{$conversaoHojePorcentagem}%")
                ->description("Pagas: {$pagasHojeInCount} / Geradas: {$pixGeradosHojeCount}")
                ->icon('heroicon-o-chart-pie')
                ->color('success'),

            Stat::make('Transações do Dia', number_format($totalMovimentosHoje, 0, ',', '.'))
                ->description("Entradas: {$entradasCriadasHoje} | Saques: {$saquesCriadosHoje}")
                ->color('info'),

            Stat::make('Transações Pagas (IN + OUT)', $brl($valorTransacoesPagasDiaTotal))
                ->description("IN {$pagasHojeInCount} | OUT {$pagasHojeOutCount}")
                ->color('success'),

            Stat::make('Taxas do Dia', $brl($taxasDiaTotal))
                ->description("IN: {$brl($taxasTransacoesDiaIn)} | OUT: {$brl($taxasTransacoesDiaOut)}")
                ->color('warning'),

            Stat::make('Total Semana (Pagas)', $brl($totalSemanaPagas))
                ->description('Pagas na semana')
                ->color('primary'),

            Stat::make('Total Mês', $brl($totalMes))
                ->description('Pagas + pendentes')
                ->color('info'),

            Stat::make('Comissão Bruta do Mês', $brl($comissaoBrutaMes))
                ->description('Taxas do mês')
                ->color('danger'),
        ];
    }
}
