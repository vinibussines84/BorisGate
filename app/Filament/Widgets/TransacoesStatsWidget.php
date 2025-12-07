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

        $inicioHojeUtc = $inicioHojeLocal->copy()->utc();
        $amanhaUtc     = $amanhaLocal->copy()->utc();

        $inicioMesUtc  = Carbon::now($tz)->startOfMonth()->utc();

        $tenantId = auth()->user()?->tenant_id;

        /* ============================================================
           🔄 CASH IN PAGAS HOJE
        ============================================================ */
        $baseHojePagasIn = Transaction::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioHojeUtc, $amanhaUtc]);

        $cashInTotal = (float)(clone $baseHojePagasIn)->sum('amount');
        $cashInCount =        (clone $baseHojePagasIn)->count();

        /* ============================================================
           🔄 CASH OUT PAGOS HOJE
        ============================================================ */
        $baseHojePagasOut = Withdraw::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('processed_at', [$inicioHojeUtc, $amanhaUtc]);

        $cashOutTotal = (float)(clone $baseHojePagasOut)->sum('amount');
        $cashOutCount =        (clone $baseHojePagasOut)->count();

        /* ============================================================
           🔄 TOTAL MOVIMENTOS PAGOS
        ============================================================ */
        $entradasPagasHoje = $cashInCount;
        $saquesPagosHoje   = $cashOutCount;

        $valorTransacoesPagasDiaTotal = $cashInTotal + $cashOutTotal;

        /* ============================================================
           🔥 TAXAS APENAS DAS TRANSACÕES PAGAS
        ============================================================ */
        $taxasTransacoesDiaIn  = (float)$baseHojePagasIn->sum('fee');
        $taxasTransacoesDiaOut = (float)$baseHojePagasOut->sum('fee_amount');

        $taxasDiaTotal = $taxasTransacoesDiaIn + $taxasTransacoesDiaOut;

        /* ============================================================
           🔥 INTERMED — total de taxas pagas pelos usuários no dia
        ============================================================ */
        $intermedHoje = $taxasDiaTotal;

        /* ============================================================
           📌 PIX GERADOS HOJE
        ============================================================ */
        $pixGeradosHojeValor = (float)Transaction::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc])
            ->sum('amount');

        $pixGeradosHojeCount = Transaction::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('direction', Transaction::DIR_IN)
            ->whereBetween('created_at', [$inicioHojeUtc, $amanhaUtc])
            ->count();

        /* ============================================================
           🔥 CONVERSÃO DO DIA
        ============================================================ */
        $transacoesPagasHoje     = $entradasPagasHoje;
        $transacoesGeradasHoje   = $pixGeradosHojeCount;

        $conversaoHojePorcentagem =
            $transacoesGeradasHoje > 0
                ? round(($transacoesPagasHoje / $transacoesGeradasHoje) * 100, 2)
                : 0;

        /* ============================================================
           🔄 COMISSÃO DO MÊS (IN)
        ============================================================ */
        $comissaoBrutaMes = (float)Transaction::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', TransactionStatus::PAID)
            ->whereBetween('paid_at', [$inicioMesUtc, $amanhaUtc])
            ->sum('fee');

        /* ============================================================
           FORMATADOR
        ============================================================ */
        $brl = fn (float $v) => 'R$ ' . number_format($v, 2, ',', '.');

        return [

            /* ============================================================
               CARD — TRANSAÇÕES PAGAS HOJE
            ============================================================ */
            Stat::make('TRANSAÇÕES PAGAS HOJE', $brl($valorTransacoesPagasDiaTotal))
                ->icon('heroicon-o-currency-dollar')
                ->description("IN {$entradasPagasHoje} | OUT {$saquesPagosHoje}")
                ->color('success'),

            /* ============================================================
               CARD — INTERMED (TAXAS PAGAS)
            ============================================================ */
            Stat::make('INTERMED', $brl($intermedHoje))
                ->description('Taxas pagas pelos usuários hoje')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('warning'),

            /* ============================================================
               CARD — PIX GERADOS HOJE
            ============================================================ */
            Stat::make('Gerado Hoje', $brl($pixGeradosHojeValor))
                ->description("{$pixGeradosHojeCount} PIX gerados")
                ->icon('heroicon-o-bolt')
                ->color('warning'),

            /* ============================================================
               CARD — CONVERSÃO
            ============================================================ */
            Stat::make('Conversão do Dia', "{$conversaoHojePorcentagem}%")
                ->description("Pagas: {$transacoesPagasHoje} / Geradas: {$transacoesGeradasHoje}")
                ->icon('heroicon-o-chart-pie')
                ->color('success'),

            /* ============================================================
               CARD — TAXAS DO DIA
            ============================================================ */
            Stat::make('Taxas do Dia (Pagas)', $brl($taxasDiaTotal))
                ->description("IN: {$brl($taxasTransacoesDiaIn)} | OUT: {$brl($taxasTransacoesDiaOut)}")
                ->icon('heroicon-o-banknotes')
                ->color('warning'),

            /* ============================================================
               CARD — COMISSÃO DO MÊS
            ============================================================ */
            Stat::make('Comissão Bruta do Mês', $brl($comissaoBrutaMes))
                ->description('Somente taxas PAGAS no mês')
                ->icon('heroicon-o-building-library')
                ->color('danger'),
        ];
    }
}
