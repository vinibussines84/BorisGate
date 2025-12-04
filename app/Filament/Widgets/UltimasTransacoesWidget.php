<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimasTransacoesWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '5s';
    protected static ?string $heading = 'Últimas 8 Transações Pagas';

    public function table(Table $table): Table
    {
        return $table
            ->poll($this->getPollingInterval())
            ->query(
                Transaction::query()
                    ->with('user')
                    ->where('status', TransactionStatus::PAID) // ✅ Enum corrigida
                    ->latest('paid_at')
                    ->limit(8)
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

                Tables\Columns\TextColumn::make('direction')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state === 'in' ? 'Entrada' : 'Saída')
                    ->icon(fn ($state) => $state === 'in' ? 'heroicon-m-arrow-down-circle' : 'heroicon-m-arrow-up-circle')
                    ->iconPosition('before')
                    ->color(fn ($state) => $state === 'in' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL', locale: 'pt_BR'),

                Tables\Columns\TextColumn::make('taxa_calculada')
                    ->label('Taxa aplicada')
                    ->getStateUsing(function ($record) {
                        if (!$record->user) {
                            return 'R$ 0,00';
                        }

                        $user = $record->user;
                        $valor = (float) $record->amount;
                        $taxa = 0;

                        if ($record->direction === 'in') {
                            $fixa = (float) ($user->tax_in_fixed ?? 0);
                            $percent = (float) ($user->tax_in_percent ?? 0);
                            $taxa = $fixa + ($valor * ($percent / 100));
                        } elseif ($record->direction === 'out') {
                            $fixa = (float) ($user->tax_out_fixed ?? 0);
                            $percent = (float) ($user->tax_out_percent ?? 0);
                            $taxa = $fixa + ($valor * ($percent / 100));
                        }

                        return 'R$ ' . number_format($taxa, 2, ',', '.');
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('txid')
                    ->label('TXID')
                    ->limit(18)
                    ->tooltip(fn ($record) => $record->txid ?? 'N/A')
                    ->copyable()
                    ->copyMessage('TXID copiado'),

                Tables\Columns\TextColumn::make('e2e_id')
                    ->label('E2E ID')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->e2e_id ?? 'N/A')
                    ->copyable()
                    ->copyMessage('E2E copiado'),

                // ✅ Corrigido: agora mostra o label e ícone de acordo com o status real
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => TransactionStatus::fromLoose($state)->label())
                    ->icon(fn ($state) => match (TransactionStatus::fromLoose($state)) {
                        TransactionStatus::PAID       => 'heroicon-o-check-circle',
                        TransactionStatus::FAILED,
                        TransactionStatus::ERROR      => 'heroicon-o-x-circle',
                        TransactionStatus::PROCESSING => 'heroicon-o-arrow-path',
                        TransactionStatus::PENDING    => 'heroicon-o-clock',
                        default                       => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn ($state) => match (TransactionStatus::fromLoose($state)) {
                        TransactionStatus::PAID       => 'success',
                        TransactionStatus::FAILED,
                        TransactionStatus::ERROR      => 'danger',
                        TransactionStatus::PROCESSING => 'info',
                        TransactionStatus::PENDING    => 'secondary',
                        default                       => 'gray',
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('paid_at')
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
