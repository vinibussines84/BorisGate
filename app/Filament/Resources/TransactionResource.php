<?php

namespace App\Filament\Resources;

use App\Enums\TransactionStatus;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\UnifiedTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class TransactionResource extends Resource
{
    protected static ?string $model = UnifiedTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Transações';
    protected static ?string $pluralModelLabel = 'Transações';
    protected static ?string $modelLabel = 'Transação';
    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool { return false; }

    /* --------------------------------------------------------------------------
     | FORM (apenas para alterar status)
     -------------------------------------------------------------------------- */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alterar status')
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
                        ->native(false),
                ]),
        ]);
    }

    /* --------------------------------------------------------------------------
     | TABLE
     -------------------------------------------------------------------------- */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                if (!($user?->is_admin ?? false) && $user?->tenant_id) {
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
                    ->toggleable(),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) =>
                        $state === 'in' ? 'Entrada' : 'Saída'
                    )
                    ->color(fn ($state) =>
                        $state === 'in' ? 'success' : 'warning'
                    ),

                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->icon(fn ($record) => match ($record->status) {
                        'pending'  => 'heroicon-o-clock',
                        'paid'     => 'heroicon-o-check-circle',
                        'med'      => 'heroicon-o-adjustments-horizontal',
                        'failed'   => 'heroicon-o-x-circle',
                        'error'    => 'heroicon-o-exclamation-triangle',
                        default    => 'heroicon-o-question-mark-circle'
                    })
                    ->color(fn ($record) => match ($record->status) {
                        'pending'  => 'warning',
                        'paid'     => 'success',
                        'med'      => 'info',
                        'failed'   => 'danger',
                        'error'    => 'gray',
                        default    => 'gray'
                    })
                    ->tooltip(fn ($record) => ucfirst($record->status)),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fee')
                    ->label('Taxa')
                    ->money('BRL', locale: 'pt_BR')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Líquido')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Meio')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Provedor')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('txid')
                    ->label('TXID / PixKey')
                    ->limit(20)
                    ->tooltip()
                    ->copyable(),

                Tables\Columns\TextColumn::make('e2e_id')
                    ->label('E2E')
                    ->limit(20)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('external_reference')
                    ->label('Ref. externa')
                    ->limit(20)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provider_transaction_id')
                    ->label('ID Prov.')
                    ->limit(20)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->since()
                    ->sortable(),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'Pendente',
                        'paid'     => 'Paga',
                        'med'      => 'Med',
                        'failed'   => 'Falha',
                        'error'    => 'Erro',
                    ]),

                Tables\Filters\SelectFilter::make('direction')
                    ->label('Tipo')
                    ->options([
                        'in'  => 'Entrada',
                        'out' => 'Saída'
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $q, $data) =>
                        $q
                            ->when($data['from'] ?? null, fn ($qq, $date) =>
                                $qq->whereDate('created_at', '>=', $date)
                            )
                            ->when($data['until'] ?? null, fn ($qq, $date) =>
                                $qq->whereDate('created_at', '<=', $date)
                            )
                    ),
            ])

            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalhes da transação')
                    ->modalWidth('3xl')
                    ->form([
                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn ($r) => ucfirst($r->status)),

                        Forms\Components\Placeholder::make('direction')
                            ->label('Tipo')
                            ->content(fn ($r) =>
                                $r->direction === 'in' ? 'Entrada' : 'Saída'
                            ),

                        Forms\Components\Placeholder::make('amount')
                            ->label('Valor')
                            ->content(fn ($r) =>
                                Number::currency($r->amount, 'BRL', locale: 'pt_BR')
                            ),

                        Forms\Components\Placeholder::make('fee')
                            ->label('Taxa')
                            ->content(fn ($r) =>
                                Number::currency($r->fee, 'BRL', locale: 'pt_BR')
                            ),

                        Forms\Components\Placeholder::make('net_amount')
                            ->label('Líquido')
                            ->content(fn ($r) =>
                                Number::currency($r->net_amount, 'BRL', locale: 'pt_BR')
                            ),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->disabled(),
                    ]),

                Tables\Actions\Action::make('alterarStatus')
                    ->label('Alterar status')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Novo status')
                            ->options([
                                'pending' => 'Pendente',
                                'paid'    => 'Paga',
                                'med'     => 'Med',
                                'failed'  => 'Falha',
                                'error'   => 'Erro',
                            ])
                            ->required(),
                    ])
                    ->disabled(fn () => true), // desativa porque unified não edita
            ])

            ->defaultSort('id', 'desc')
            ->deferLoading();
    }

    /* --------------------------------------------------------------------------
     | PAGES
     -------------------------------------------------------------------------- */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view'  => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'txid', 'e2e_id', 'external_reference'];
    }
}
