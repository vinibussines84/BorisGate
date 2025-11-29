<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TransactionStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $q = Transaction::query();

        if (! ($user?->is_admin ?? false) && ($user?->tenant_id)) {
            $q->where('tenant_id', $user->tenant_id);
        }

        $today = now()->startOfDay();

        $entradas = (clone $q)->where('direction','in')->sum('amount') ?? 0;
        $saidas   = (clone $q)->where('direction','out')->sum('amount') ?? 0;
        $pagasHoje = (clone $q)->where('status','paid')->where('paid_at','>=',$today)->sum('amount') ?? 0;
        $pendentes = (clone $q)->whereIn('status',['pending','processing'])->count();

        return [
            Stat::make('Entradas (R$)', number_format($entradas, 2, ',', '.'))->description('Total de entradas'),
            Stat::make('Saídas (R$)', number_format($saidas, 2, ',', '.'))->description('Total de saídas'),
            Stat::make('Pagas hoje (R$)', number_format($pagasHoje, 2, ',', '.'))->description('Desde 00:00'),
            Stat::make('Pendentes', (string) $pendentes)->description('Aguardando processamento'),
        ];
    }
}
