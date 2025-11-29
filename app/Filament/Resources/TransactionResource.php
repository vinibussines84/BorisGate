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
    //protected static ?string $navigationGroup = 'Financeiro';
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
                            TransactionStatus::PENDENTE->value => 'Pendente',
                            TransactionStatus::PAGA->value     => 'Paga',
                            TransactionStatus::MED->value      => 'Med',
                            TransactionStatus::FALHA->value    => 'Falha',
                            TransactionStatus::ERRO->value     => 'Erro',
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

                // Novo: Usuário ao lado do ID
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->direction === 'in' ? 'Entrada' : 'Saída')
                    ->color(fn ($record) => $record->direction === 'in' ? 'success' : 'warning'),

                // Status em ícones (sem badge)
                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->icon(function ($record) {
                        $value = $record->status instanceof \App\Enums\TransactionStatus
                            ? $record->status->value
                            : (string) $record->status;

                        return match ($value) {
                            TransactionStatus::PENDENTE->value => 'heroicon-o-clock',
                            TransactionStatus::PAGA->value     => 'heroicon-o-check-circle',
                            TransactionStatus::MED->value      => 'heroicon-o-adjustments-horizontal',
                            TransactionStatus::FALHA->value    => 'heroicon-o-x-circle',
                            TransactionStatus::ERRO->value     => 'heroicon-o-exclamation-triangle',
                            default                            => 'heroicon-o-question-mark-circle',
                        };
                    })
                    ->color(function ($record) {
                        $value = $record->status instanceof \App\Enums\TransactionStatus
                            ? $record->status->value
                            : (string) $record->status;

                        return match ($value) {
                            TransactionStatus::PENDENTE->value => 'warning',
                            TransactionStatus::PAGA->value     => 'success',
                            TransactionStatus::MED->value      => 'info',
                            TransactionStatus::FALHA->value    => 'danger',
                            TransactionStatus::ERRO->value     => 'gray',
                            default                            => 'gray',
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
                    ->state(fn ($record) => (float) $record->amount - (float) $record->fee)
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Meio')
                    ->badge()
                    ->formatStateUsing(fn ($record) => strtoupper((string) $record->method))
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
                    ->tooltip(fn ($record) => $record->txid)
                    ->copyable()
                    ->copyMessage('TXID copiado')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('e2e_id')
                    ->label('E2E')
                    ->limit(22)
                    ->tooltip(fn ($record) => $record->e2e_id)
                    ->copyable()
                    ->copyMessage('E2E copiado')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('external_reference')
                    ->label('Ref. externa')
                    ->limit(18)
                    ->tooltip(fn ($record) => $record->external_reference)
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('provider_transaction_id')
                    ->label('ID do provedor')
                    ->limit(18)
                    ->tooltip(fn ($record) => $record->provider_transaction_id)
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
                        TransactionStatus::PENDENTE->value => 'Pendente',
                        TransactionStatus::PAGA->value     => 'Paga',
                        TransactionStatus::MED->value      => 'Med',
                        TransactionStatus::FALHA->value    => 'Falha',
                        TransactionStatus::ERRO->value     => 'Erro',
                    ]),
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Tipo')
                    ->options(['in' => 'Entrada', 'out' => 'Saída']),
                Tables\Filters\SelectFilter::make('method')
                    ->label('Meio')
                    ->options(fn () => \App\Models\Transaction::query()
                        ->whereNotNull('method')
                        ->distinct()
                        ->pluck('method', 'method')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('provider')
                    ->label('Provedor')
                    ->options(fn () => \App\Models\Transaction::query()
                        ->whereNotNull('provider')
                        ->distinct()
                        ->pluck('provider', 'provider')
                        ->toArray()),
                Tables\Filters\TernaryFilter::make('has_txid')
                    ->label('Tem TXID')
                    ->trueLabel('Somente com TXID')
                    ->falseLabel('Sem TXID')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('txid'),
                        false: fn (Builder $q) => $q->whereNull('txid'),
                        blank: fn (Builder $q) => $q,
                    ),
                Tables\Filters\TernaryFilter::make('has_e2e')
                    ->label('Tem E2E')
                    ->trueLabel('Somente com E2E')
                    ->falseLabel('Sem E2E')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('e2e_id'),
                        false: fn (Builder $q) => $q->whereNull('e2e_id'),
                        blank: fn (Builder $q) => $q,
                    ),
                Tables\Filters\Filter::make('date_range')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalhes da transação')
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Section::make('Resumo')
                            ->columns(3)
                            ->schema([
                                Forms\Components\Placeholder::make('status_label')
                                    ->label('Status')
                                    ->content(fn ($r) => $r?->status_label ?? '—'),
                                Forms\Components\Placeholder::make('direction_label')
                                    ->label('Tipo')
                                    ->content(fn ($r) => $r?->direction === 'in' ? 'Entrada' : 'Saída'),
                                Forms\Components\Placeholder::make('currency')
                                    ->label('Moeda')
                                    ->content(fn ($r) => $r?->currency ?? '—'),
                                Forms\Components\Placeholder::make('amount_fmt')
                                    ->label('Valor')
                                    ->content(fn ($r) => Number::currency((float) $r->amount, 'BRL', locale: 'pt_BR')),
                                Forms\Components\Placeholder::make('fee_fmt')
                                    ->label('Taxa')
                                    ->content(fn ($r) => Number::currency((float) $r->fee, 'BRL', locale: 'pt_BR')),
                                Forms\Components\Placeholder::make('net_amount_fmt')
                                    ->label('Líquido')
                                    ->content(function ($r) {
                                        $liq = (float) $r->amount - (float) $r->fee;
                                        return Number::currency($liq, 'BRL', locale: 'pt_BR');
                                    }),
                            ]),
                        Forms\Components\Section::make('Identificadores')
                            ->columns(3)
                            ->schema([
                                Forms\Components\Placeholder::make('external_reference')->label('Ref. externa')
                                    ->content(fn ($r) => (string) ($r?->external_reference ?? '—')),
                                Forms\Components\Placeholder::make('provider_transaction_id')->label('ID do provedor')
                                    ->content(fn ($r) => (string) ($r?->provider_transaction_id ?? '—')),
                                Forms\Components\Placeholder::make('txid')->label('TXID')
                                    ->content(fn ($r) => (string) ($r?->txid ?? '—')),
                                Forms\Components\Placeholder::make('e2e_id')->label('E2E')
                                    ->content(fn ($r) => (string) ($r?->e2e_id ?? '—')),
                                Forms\Components\Placeholder::make('method')->label('Meio')
                                    ->content(fn ($r) => strtoupper((string) ($r?->method ?? '—'))),
                                Forms\Components\Placeholder::make('provider')->label('Provedor')
                                    ->content(fn ($r) => (string) ($r?->provider ?? '—')),
                            ]),
                        Forms\Components\Section::make('Datas')
                            ->columns(3)
                            ->schema([
                                Forms\Components\Placeholder::make('authorized_at')->label('Autorizado em')
                                    ->content(fn ($r) => optional($r?->authorized_at)->format('d/m/Y H:i') ?? '—'),
                                Forms\Components\Placeholder::make('paid_at')->label('Pago em')
                                    ->content(fn ($r) => optional($r?->paid_at)->format('d/m/Y H:i') ?? '—'),
                                Forms\Components\Placeholder::make('refunded_at')->label('Estornado em')
                                    ->content(fn ($r) => optional($r?->refunded_at)->format('d/m/Y H:i') ?? '—'),
                                Forms\Components\Placeholder::make('canceled_at')->label('Cancelado em')
                                    ->content(fn ($r) => optional($r?->canceled_at)->format('d/m/Y H:i') ?? '—'),
                            ]),
                        Forms\Components\Section::make('Descrição / Payload')
                            ->schema([
                                Forms\Components\Placeholder::make('description')
                                    ->label('Descrição')
                                    ->content(fn ($r) => (string) ($r?->description ?? '—')),
                                Forms\Components\Textarea::make('provider_payload_json')
                                    ->label('Payload do provedor (JSON)')
                                    ->rows(14)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record) {
                                        $json = $record?->provider_payload
                                            ? json_encode($record->provider_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                            : '';
                                        $component->state($json);
                                    })
                                    ->extraAttributes(['class' => 'font-mono text-xs']),
                            ]),
                    ]),
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
                                TransactionStatus::PENDENTE->value => 'Pendente',
                                TransactionStatus::PAGA->value     => 'Paga',
                                TransactionStatus::MED->value      => 'Med',
                                TransactionStatus::FALHA->value    => 'Falha',
                                TransactionStatus::ERRO->value     => 'Erro',
                            ])
                            ->required()
                            ->native(false)
                            ->default(fn ($record) => $record?->status?->value ?? TransactionStatus::PENDENTE->value)
                            ->helperText('O ajuste de saldos acontecerá automaticamente ao salvar.'),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        $record->status = $data['status'];
                        $record->save();
                    }),
            ])
            ->bulkActions([])
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
