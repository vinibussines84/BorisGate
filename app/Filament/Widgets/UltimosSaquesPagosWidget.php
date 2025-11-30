<?php

namespace App\Filament\Widgets;

use App\Models\Withdraw;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimosSaquesPagosWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $pollingInterval = '5s';
    protected static ?string $heading = 'Últimos 5 Saques Pagos';

    public function table(Table $table): Table
    {
        return $table
            ->poll($this->getPollingInterval())
            ->query(
                Withdraw::query()
                    ->with('user')
                    ->where('status', Withdraw::STATUS_PAID)
                    ->latest('processed_at')
                    ->limit(5)
            )
            ->columns([

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->getStateUsing(function ($record) {
                        if (!$record->user) {
                            return 'Sem usuário';
                        }
                        $parts = explode(' ', trim($record->user->name));
                        return $parts[0] ?? $record->user->name;
                    }),

                Tables\Columns\TextColumn::make('pixkey_masked')
                    ->label('Chave PIX')
                    ->tooltip(fn ($record) => $record->pixkey ?? 'N/A'),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Valor Bruto')
                    ->money('BRL', locale: 'pt_BR'),

                Tables\Columns\TextColumn::make('fee_amount')
                    ->label('Taxa')
                    ->money('BRL', locale: 'pt_BR')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor Líquido')
                    ->money('BRL', locale: 'pt_BR')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status_label')
                    ->label('Status')
                    ->color(fn ($record) => $record->status_color)
                    ->icon('heroicon-o-check-circle')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i:s', timezone: 'America/Sao_Paulo'),

            ])
            ->paginated(false);
    }

    public function getPollingInterval(): string
    {
        return '5s';
    }

    public function getColumnSpan(): string|int
    {
        return 'full';
    }
}
