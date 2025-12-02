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

        $inicioHojeLocal = Carbon::today($tz);
        $amanhaLocal     = (clone $inicioHojeLocal)->addDay();
        $inicioHojeUtc   = $inicioHojeLocal->copy()->utc();
        $amanhaUtc       = $amanhaLocal->copy()->utc();

        $inicioSemanaUtc = Carbon::now($tz)->startOfWeek()->utc();
        $inicioMesUtc    = Carbon::now($tz)->startOfMonth()->utc();

        $tenantId = auth()->user()?->tenant_id;

        /* ============================================================
           🔄 CASH IN PAGAS HOJE
        ============================================================ */
        $baseHojePagasIn = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$inicioHojeUtc, $amanhaUtc]);

        $cashInTotal = (float)(clone $baseHojePagasIn)->sum('amount');
        $cashInCount =        (clone $baseHojePagasIn)->count();

        /* 👇 DESCONTO SOMENTE VISUAL: 1.5% + R$0,10 POR TRANSAÇÃO */
        $descontoPercentual = $cashInTotal * 0.015;
        $descontoFixo = $cashInCount * 0.10;
        $cashInTotalLiquidoVisual = $cashInTotal - ($descontoPercentual + $descontoFixo);

        /* CASH OUT HOJE */
        $baseHojeCriadasOut = Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc]);

        $cashOutTotal = (float)(clone $baseHojeCriadasOut)->sum('amount');
        $cashOutCount =        (clone $baseHojeCriadasOut)->count();

        $entradasCriadasHoje = $cashInCount;
        $saquesCriadosHoje   = $cashOutCount;
        $totalMovimentosHoje = $entradasCriadasHoje + $saquesCriadosHoje;

        /* PAGAS DO DIA */
        $valorTransacoesPagasDiaIn = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('amount');

        $valorTransacoesPagasDiaOut = (float)Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('amount');

        $valorTransacoesPagasDiaTotal = $valorTransacoesPagasDiaIn + $valorTransacoesPagasDiaOut;

        $pagasHojeInCount = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$inicioHojeUtc, $amanhaUtc])
            ->count();

        $pagasHojeOutCount = Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHojeUtc, $amanhaUtc])
            ->count();

        /* PIX GERADOS */
        $pixGeradosHojeValor = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('amount');

        $pixGeradosHojeCount = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc])
            ->count();

        /* CONVERSÃO */
        $transacoesPagasHoje = $pagasHojeInCount;
        $transacoesGeradasHoje = $pixGeradosHojeCount;

        $conversaoHojePorcentagem = $transacoesGeradasHoje > 0
            ? round(($transacoesPagasHoje / $transacoesGeradasHoje) * 100, 2)
            : 0;

        /* TAXAS */
        $taxasTransacoesDiaIn = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('fee');

        $taxasTransacoesDiaOut = (float)Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('fee_amount');

        $taxasDiaTotal = $taxasTransacoesDiaIn + $taxasTransacoesDiaOut;

        /* SEMANA / MÊS */
        $totalSemanaPagas = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$inicioSemanaUtc, $amanhaUtc])
            ->sum('amount');

        $totalMes = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$inicioMesUtc, $amanhaUtc])
            ->whereIn('status', [TransactionStatus::PAGA, TransactionStatus::PENDENTE])
            ->sum('amount');

        $comissaoBrutaMes = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAGA)
            ->whereBetween('paid_at', [$inicioMesUtc, $amanhaUtc])
            ->sum('fee');

        /* MEDs */
        $medsHojeCount = Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::MED)
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc])
            ->count();

        $medsHojeValor = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::MED)
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('amount');

        /* FORMATADOR */
        $brl = fn(float $v) => 'R$ ' . number_format($v, 2, ',', '.');

        return [

            /* 🔥 AGORA EXIBINDO O VALOR LÍQUIDO VISUAL */
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
                ->description("Pagas: {$transacoesPagasHoje} / Geradas: {$transacoesGeradasHoje}")
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

            Stat::make('MEDS HOJE', $brl($medsHojeValor))
                ->description("{$medsHojeCount} transações MED")
                ->icon('heroicon-o-shield-exclamation')
                ->color('gray'),

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
