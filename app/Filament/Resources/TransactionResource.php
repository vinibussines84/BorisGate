<?php

namespace App\Filament\Resources;

use App\Enums\TransactionStatus;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Transações';
    protected static ?string $pluralModelLabel = 'Transações';
    protected static ?string $modelLabel = 'Transação';
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alterar status')
                ->description('Atualize o status da transação. A carteira é ajustada automaticamente.')
                ->icon('heroicon-o-adjustments-vertical')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            TransactionStatus::PENDING->value    => 'Pendente',
                            TransactionStatus::PAID->value       => 'Paga',
                            TransactionStatus::PROCESSING->value => 'Em Processamento',
                            TransactionStatus::FAILED->value     => 'Falha',
                            TransactionStatus::ERROR->value      => 'Erro Interno',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('O ajuste de saldos (disponível/bloqueado) ocorre via Observer.'),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if (! ($user?->is_admin ?? false) && ($user?->tenant_id)) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            })
            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($record) =>
                        $record->direction === 'in' ? 'Entrada' : 'Saída'
                    )
                    ->color(fn ($record) =>
                        $record->direction === 'in' ? 'success' : 'warning'
                    ),

                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->icon(function ($record) {
                        $value = $record->status instanceof TransactionStatus
                            ? $record->status->value
                            : (string) $record->status;

                        return match ($value) {
                            TransactionStatus::PENDING->value    => 'heroicon-o-clock',
                            TransactionStatus::PAID->value       => 'heroicon-o-check-circle',
                            TransactionStatus::PROCESSING->value => 'heroicon-o-arrow-path',
                            TransactionStatus::FAILED->value     => 'heroicon-o-x-circle',
                            TransactionStatus::ERROR->value      => 'heroicon-o-exclamation-triangle',
                            default                              => 'heroicon-o-question-mark-circle',
                        };
                    })
                    ->color(function ($record) {
                        $value = $record->status instanceof TransactionStatus
                            ? $record->status->value
                            : (string) $record->status;

                        return match ($value) {
                            TransactionStatus::PENDING->value    => 'warning',
                            TransactionStatus::PAID->value       => 'success',
                            TransactionStatus::PROCESSING->value => 'info',
                            TransactionStatus::FAILED->value     => 'danger',
                            TransactionStatus::ERROR->value      => 'gray',
                            default                              => 'gray',
                        };
                    })
                    ->tooltip(fn ($record) => $record->status_label ?? '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fee')
                    ->label('Taxa')
                    ->money('BRL', locale: 'pt_BR')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Líquido')
                    ->state(fn ($record) =>
                        (float) $record->amount - (float) $record->fee
                    )
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Meio')
                    ->badge()
                    ->formatStateUsing(fn ($record) =>
                        strtoupper((string) $record->method)
                    )
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Provedor')
                    ->badge()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('txid')
                    ->label('TXID')
                    ->limit(22)
                    ->tooltip(fn ($record) => $record->txid ?? '—')
                    ->copyable()
                    ->copyMessage('TXID copiado')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('e2e_id')
                    ->label('E2E')
                    ->limit(22)
                    ->tooltip(fn ($record) => $record->e2e_id ?? '—')
                    ->copyable()
                    ->copyMessage('E2E copiado')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('external_reference')
                    ->label('Ref. externa')
                    ->limit(18)
                    ->tooltip(fn ($record) => $record->external_reference ?? '—')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('provider_transaction_id')
                    ->label('ID do provedor')
                    ->limit(18)
                    ->tooltip(fn ($record) => $record->provider_transaction_id ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        TransactionStatus::PENDING->value    => 'Pendente',
                        TransactionStatus::PAID->value       => 'Paga',
                        TransactionStatus::PROCESSING->value => 'Em Processamento',
                        TransactionStatus::FAILED->value     => 'Falha',
                        TransactionStatus::ERROR->value      => 'Erro Interno',
                    ]),

                Tables\Filters\SelectFilter::make('direction')
                    ->label('Tipo')
                    ->options(['in' => 'Entrada', 'out' => 'Saída']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('alterarStatus')
                    ->label('Alterar status')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->modalHeading('Alterar status da transação')
                    ->modalSubmitActionLabel('Salvar status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Novo status')
                            ->options([
                                TransactionStatus::PENDING->value    => 'Pendente',
                                TransactionStatus::PAID->value       => 'Paga',
                                TransactionStatus::PROCESSING->value => 'Em Processamento',
                                TransactionStatus::FAILED->value     => 'Falha',
                                TransactionStatus::ERROR->value      => 'Erro Interno',
                            ])
                            ->required()
                            ->native(false)
                            ->default(fn ($record) =>
                                $record?->status?->value ?? TransactionStatus::PENDING->value
                            )
                            ->helperText('O ajuste de saldos acontecerá automaticamente ao salvar.'),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        $record->status = $data['status'];
                        $record->save();
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->deferLoading();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view'  => Pages\ViewTransaction::route('/{record}'),
            'edit'  => Pages\EditTransaction::route('/{record}/editar'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'id', 'txid', 'e2e_id', 'provider_transaction_id', 'external_reference', 'description',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
