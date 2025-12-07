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
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioHojeUtc, $amanhaUtc]);

        $cashInTotal = (float)(clone $baseHojePagasIn)->sum('amount');
        $cashInCount =        (clone $baseHojePagasIn)->count();

        // Desconto apenas visual
        $descontoPercentual = $cashInTotal * 0.015;
        $descontoFixo = $cashInCount * 0.10;
        $cashInTotalLiquidoVisual = $cashInTotal - ($descontoPercentual + $descontoFixo);

        /* ============================================================
           🔄 CASH OUT PAGOS HOJE
        ============================================================ */
        $baseHojePagasOut = Withdraw::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHojeUtc, $amanhaUtc]);

        $cashOutTotal = (float)(clone $baseHojePagasOut)->sum('amount');
        $cashOutCount =        (clone $baseHojePagasOut)->count();

        /* ============================================================
           🔄 TOTAL DE MOVIMENTOS PAGOS HOJE
        ============================================================ */
        $entradasPagasHoje = $cashInCount;
        $saquesPagosHoje   = $cashOutCount;
        $totalMovimentosHoje = $entradasPagasHoje + $saquesPagosHoje;

        /* ============================================================
           📌 PAGAS DO DIA — SOMENTE PAGAS (IN + OUT)
        ============================================================ */

        $valorTransacoesPagasDiaIn = $cashInTotal;

        $valorTransacoesPagasDiaOut = (float)(clone $baseHojePagasOut)->sum('amount');

        $valorTransacoesPagasDiaTotal = $valorTransacoesPagasDiaIn + $valorTransacoesPagasDiaOut;

        /* ============================================================
           🔥 TAXAS DO DIA — SOMENTE PAGAS (IN + OUT)
        ============================================================ */

        $taxasTransacoesDiaIn = (float)$baseHojePagasIn->sum('fee');

        $taxasTransacoesDiaOut = (float)$baseHojePagasOut->sum('fee_amount');

        $taxasDiaTotal = $taxasTransacoesDiaIn + $taxasTransacoesDiaOut;

        /* ============================================================
           📌 INTERMED — Quanto os usuários pagaram de taxa por dia
           (taxa paga IN + taxa paga OUT)
        ============================================================ */
        $intermedHoje = $taxasDiaTotal;

        /* ============================================================
           📌 PIX GERADOS HOJE
        ============================================================ */
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

        /* ============================================================
           🔥 CONVERSÃO
        ============================================================ */
        $transacoesPagasHoje = $entradasPagasHoje;
        $transacoesGeradasHoje = $pixGeradosHojeCount;

        $conversaoHojePorcentagem = $transacoesGeradasHoje > 0
            ? round(($transacoesPagasHoje / $transacoesGeradasHoje) * 100, 2)
            : 0;

        /* ============================================================
           🔄 TAXAS DO MÊS
        ============================================================ */

        $comissaoBrutaMes = (float)Transaction::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioMesUtc, $amanhaUtc])
            ->sum('fee');

        /* ============================================================
           FORMATADOR
        ============================================================ */

        $brl = fn(float $v) => 'R$ ' . number_format($v, 2, ',', '.');

        return [

            /* ============================================================
               CARD 1 — TRANSAÇÕES PAGAS HOJE
            ============================================================ */
            Stat::make('TRANSAÇÕES PAGAS HOJE', $brl($valorTransacoesPagasDiaTotal))
                ->icon('heroicon-o-currency-dollar')
                ->description("IN {$entradasPagasHoje}  |  OUT {$saquesPagosHoje}")
                ->color('success'),

            /* ============================================================
               CARD 2 — INTERMED (TAXAS PAGAS)
            ============================================================ */
            Stat::make('INTERMED', $brl($intermedHoje))
                ->description('Taxas pagas pelos usuários hoje')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning'),

            /* ============================================================
               CARD 3 — PIX GERADOS HOJE
            ============================================================ */
            Stat::make('Gerado Hoje', $brl($pixGeradosHojeValor))
                ->description("{$pixGeradosHojeCount} PIX gerados")
                ->icon('heroicon-o-bolt')
                ->color('warning'),

            /* ============================================================
               CARD 4 — CONVERSÃO DO DIA
            ============================================================ */
            Stat::make('Conversão do Dia', "{$conversaoHojePorcentagem}%")
                ->description("Pagas: {$transacoesPagasHoje} / Geradas: {$transacoesGeradasHoje}")
                ->icon('heroicon-o-chart-pie')
                ->color('success'),

            /* ============================================================
               CARD 5 — TAXAS DO DIA
            ============================================================ */
            Stat::make('Taxas do Dia (Pagas)', $brl($taxasDiaTotal))
                ->description("IN: {$brl($taxasTransacoesDiaIn)} | OUT: {$brl($taxasTransacoesDiaOut)}")
                ->icon('heroicon-o-cash')
                ->color('warning'),

            /* ============================================================
               CARD 6 — COMISSÃO BRUTA DO MÊS
            ============================================================ */
            Stat::make('Comissão Bruta do Mês', $brl($comissaoBrutaMes))
                ->description('Taxas do mês (IN)')
                ->icon('heroicon-o-banknotes')
                ->color('danger'),
        ];
    }
}
