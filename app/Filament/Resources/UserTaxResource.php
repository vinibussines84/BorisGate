<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserTaxResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class UserTaxResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Taxas';
    protected static ?string $pluralLabel     = 'Taxas';
    protected static ?string $modelLabel      = 'Taxas por Usuário';
   // protected static ?string $navigationGroup = 'Administração';
    protected static ?int    $navigationSort  = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Usuário')
                ->description('Dados básicos (somente leitura).')
                ->schema([
                    Forms\Components\TextInput::make('nome_completo')
                        ->label('Nome')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(6),

                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(6),
                ])->columns(12),

            Forms\Components\Section::make('Cash-in')
                ->description('Taxas aplicadas nas ENTRADAS (recebimentos).')
                ->schema([
                    Forms\Components\Toggle::make('tax_in_enabled')
                        ->label('Ativar taxa de cash-in')
                        ->live(),

                    Forms\Components\Radio::make('tax_in_mode')
                        ->label('Modo de taxa (cash-in)')
                        ->options(['fixo' => 'Fixo (R$)', 'percentual' => 'Porcentagem (%)'])
                        ->inline()
                        ->default('percentual')
                        ->live()
                        ->disabled(fn (Get $get) => ! $get('tax_in_enabled')),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('tax_in_fixed')
                            ->label('Valor fixo (R$)')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->requiredIf('tax_in_mode', 'fixo')
                            ->visible(fn (Get $get) => $get('tax_in_enabled') && $get('tax_in_mode') === 'fixo'),

                        Forms\Components\TextInput::make('tax_in_percent')
                            ->label('Porcentagem (%)')
                            ->suffix('%')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->requiredIf('tax_in_mode', 'percentual')
                            ->visible(fn (Get $get) => $get('tax_in_enabled') && $get('tax_in_mode') === 'percentual'),
                    ]),
                ]),

            Forms\Components\Section::make('Cash-out')
                ->description('Taxas aplicadas nas SAÍDAS (saques/transferências).')
                ->schema([
                    Forms\Components\Toggle::make('tax_out_enabled')
                        ->label('Ativar taxa de cash-out')
                        ->live(),

                    Forms\Components\Radio::make('tax_out_mode')
                        ->label('Modo de taxa (cash-out)')
                        ->options(['fixo' => 'Fixo (R$)', 'percentual' => 'Porcentagem (%)'])
                        ->inline()
                        ->default('percentual')
                        ->live()
                        ->disabled(fn (Get $get) => ! $get('tax_out_enabled')),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('tax_out_fixed')
                            ->label('Valor fixo (R$)')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->requiredIf('tax_out_mode', 'fixo')
                            ->visible(fn (Get $get) => $get('tax_out_enabled') && $get('tax_out_mode') === 'fixo'),

                        Forms\Components\TextInput::make('tax_out_percent')
                            ->label('Porcentagem (%)')
                            ->suffix('%')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->requiredIf('tax_out_mode', 'percentual')
                            ->visible(fn (Get $get) => $get('tax_out_enabled') && $get('tax_out_mode') === 'percentual'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Taxas por usuário')
            ->searchPlaceholder('Buscar por nome ou e-mail…')
            ->columns([
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Usuário')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (User $record) => $record->email, position: 'below'),

                // IN
                Tables\Columns\IconColumn::make('tax_in_enabled')
                    ->label('In')
                    ->boolean(),

                Tables\Columns\TextColumn::make('tax_in_mode')
                    ->label('Modo In')
                    ->formatStateUsing(fn ($state) => $state === 'fixo' ? 'Fixo' : 'Percentual')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tax_in_fixed')
                    ->label('Fixo In')
                    ->money('BRL', locale: 'pt_BR')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_in_percent')
                    ->label('% In')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float)$state, 2, ',', '.') . '%' : null)
                    ->toggleable()
                    ->sortable(),

                // OUT
                Tables\Columns\IconColumn::make('tax_out_enabled')
                    ->label('Out')
                    ->boolean(),

                Tables\Columns\TextColumn::make('tax_out_mode')
                    ->label('Modo Out')
                    ->formatStateUsing(fn ($state) => $state === 'fixo' ? 'Fixo' : 'Percentual')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tax_out_fixed')
                    ->label('Fixo Out')
                    ->money('BRL', locale: 'pt_BR')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_out_percent')
                    ->label('% Out')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float)$state, 2, ',', '.') . '%' : null)
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('q')
                    ->form([
                        Forms\Components\TextInput::make('q')
                            ->label('Buscar')
                            ->placeholder('Nome ou e-mail')
                            ->prefixIcon('heroicon-o-magnifying-glass'),
                    ])
                    ->query(function ($query, array $data) {
                        if (! filled($data['q'] ?? null)) return;
                        $q = trim($data['q']);
                        $query->where(function ($sub) use ($q) {
                            $sub->where('nome_completo', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                    })
                    ->indicateUsing(fn (array $data) => filled($data['q'] ?? null) ? "Busca: {$data['q']}" : null),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square'),
            ])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserTaxes::route('/'),
            'edit'  => Pages\EditUserTax::route('/{record}/edit'),
        ];
    }
}
