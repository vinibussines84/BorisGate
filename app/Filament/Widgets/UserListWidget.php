<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UserListWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Lista de Usuários';
    protected static ?string $pollingInterval = '10s';

    public function table(Table $table): Table
    {
        return $table
            ->poll(static::$pollingInterval)
            ->query(
                User::query()
                    ->with(['cashinProvider', 'cashoutProvider'])
                    ->orderByDesc('amount_available')
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('cashinProvider.name')
                    ->label('Provedor Cash-In')
                    ->default('—'),

                Tables\Columns\TextColumn::make('cashoutProvider.name')
                    ->label('Provedor Cash-Out')
                    ->default('—'),

                Tables\Columns\TextColumn::make('amount_available')
                    ->label('Saldo Disponível')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_retained')
                    ->label('Saldo Retido')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('blocked_amount')
                    ->label('Bloqueado')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_in_label')
                    ->label('Taxa CashIn'),

                Tables\Columns\TextColumn::make('tax_out_label')
                    ->label('Taxa CashOut'),

                Tables\Columns\TextColumn::make('user_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state === 'ativo' ? 'success' : 'gray'),
            ])
            ->paginated(false);
    }

    public function getColumnSpan(): string|int
    {
        return 'full';
    }
}