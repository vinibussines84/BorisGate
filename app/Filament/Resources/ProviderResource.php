<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Models\Provider;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

// ✅ Contratos e Factory (ajuste os namespaces se necessário)
use App\Domain\Payments\ProviderFactory;
use App\Domain\Payments\Contracts\InboundPaymentsProvider;
// Opcional (se já existir no seu projeto). Se não existir, a ação OUT avisa.
use App\Domain\Payments\Contracts\OutboundPaymentsProvider;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationLabel = 'Providers';
    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identidade')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->helperText('Ex.: getpay, ecomovi, horizon, veltraxpay')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(60),
                ]),

            Forms\Components\Section::make('Classe do Serviço & Aliases')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('service_class')
                        ->label('Service Class (adapter)')
                        ->helperText('Ex.: App\Services\VeltraxPay\VeltraxPayInbound')
                        ->required()
                        ->maxLength(180),

                    Forms\Components\TextInput::make('provider_in')
                        ->label('Alias IN')
                        ->placeholder('#01VEL')
                        ->maxLength(80),

                    Forms\Components\TextInput::make('provider_out')
                        ->label('Alias OUT')
                        ->placeholder('#02-vel')
                        ->maxLength(80),

                    Forms\Components\Toggle::make('active')
                        ->label('Ativo')
                        ->default(true)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Config')
                ->schema([
                    Forms\Components\KeyValue::make('config')
                        ->label('Config (JSON)')
                        ->reorderable()
                        ->keyLabel('chave')
                        ->valueLabel('valor')
                        ->helperText('Parâmetros globais/credenciais do provider (ex.: base_url, client_id, client_secret, token_ttl).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Ativo'),

                Tables\Columns\TextColumn::make('service_class')
                    ->label('Service')
                    ->limit(28)
                    ->tooltip(fn (Provider $record) => $record->service_class),

                Tables\Columns\TextColumn::make('provider_in')
                    ->label('IN')
                    ->badge()
                    ->tooltip('Alias de entrada'),

                Tables\Columns\TextColumn::make('provider_out')
                    ->label('OUT')
                    ->badge()
                    ->tooltip('Alias de saída'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Atualizado'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Ativo')
                    ->placeholder('Todos')
                    ->trueLabel('Somente ativos')
                    ->falseLabel('Somente inativos')
                    ->queries(
                        true: fn ($q) => $q->where('active', true),
                        false: fn ($q) => $q->where('active', false),
                        blank: fn ($q) => $q
                    ),
            ])
            ->actions([
                // ===================== AÇÃO IN (DEPÓSITO/PIX) =====================
                Tables\Actions\Action::make('executar_in')
                    ->label('Executar IN')
                    ->color('success')
                    ->icon('heroicon-o-play-circle')
                    ->modalHeading(fn (Provider $record) => 'Executar IN ' . ($record->provider_in ? "({$record->provider_in})" : ''))
                    ->modalSubmitActionLabel('Executar')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuário')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Usuário sob o qual a operação será executada.'),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor (BRL)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),

                        Forms\Components\TextInput::make('external_id')
                            ->label('External ID')
                            ->default(fn () => 'test_' . Str::random(8))
                            ->maxLength(191)
                            ->required()
                            ->helperText('ID único para idempotência.'),

                        Forms\Components\TextInput::make('clientCallbackUrl')
                            ->label('Callback URL')
                            ->url()
                            ->default('https://seuservidor.com/callback')
                            ->required(),

                        Forms\Components\Fieldset::make('Payer')
                            ->schema([
                                Forms\Components\TextInput::make('payer.name')->label('Nome')->required(),
                                Forms\Components\TextInput::make('payer.email')->label('Email')->email()->required(),
                                Forms\Components\TextInput::make('payer.document')->label('Documento')->required(),
                                Forms\Components\TextInput::make('payer.phone')->label('Telefone'),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (array $data, Provider $record) {
                        // Guardas rápidas
                        if (!$record->active) {
                            throw new \RuntimeException('Provider inativo.');
                        }
                        if (empty($record->provider_in)) {
                            throw new \RuntimeException('Alias IN não configurado neste provider.');
                        }

                        $user = User::findOrFail($data['user_id']);

                        try {
                            // Instancia adapter IN (usa $record->service_class e $record->config)
                            $inbound = ProviderFactory::makeInbound($record);

                            if (!($inbound instanceof InboundPaymentsProvider)) {
                                throw new \RuntimeException('Service class não implementa InboundPaymentsProvider.');
                            }

                            $payload = [
                                'amount'            => (float) $data['amount'],
                                'external_id'       => $data['external_id'],
                                'clientCallbackUrl' => $data['clientCallbackUrl'],
                                'payer'             => [
                                    'name'     => Arr::get($data, 'payer.name'),
                                    'email'    => Arr::get($data, 'payer.email'),
                                    'document' => Arr::get($data, 'payer.document'),
                                    'phone'    => Arr::get($data, 'payer.phone'),
                                ],
                            ];

                            $result = $inbound->createDeposit($payload);

                            \Filament\Notifications\Notification::make()
                                ->title('IN concluído')
                                ->body('Operação executada com sucesso.')
                                ->success()
                                ->send();

                            // Opcional: mostrar detalhes em modal de info
                            \Filament\Notifications\Notification::make()
                                ->title('Resposta')
                                ->body(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Falha no IN')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            throw $e;
                        }
                    }),

                // ===================== AÇÃO OUT (PAYOUT/SAQUE) =====================
                Tables\Actions\Action::make('executar_out')
                    ->label('Executar OUT')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-up-on-square-stack')
                    ->modalHeading(fn (Provider $record) => 'Executar OUT ' . ($record->provider_out ? "({$record->provider_out})" : ''))
                    ->modalSubmitActionLabel('Executar')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuário')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor (BRL)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),

                        Forms\Components\TextInput::make('external_id')
                            ->label('External ID')
                            ->default(fn () => 'out_' . Str::random(8))
                            ->maxLength(191)
                            ->required(),

                        Forms\Components\KeyValue::make('beneficiary')
                            ->label('Beneficiário (dados livres)')
                            ->keyLabel('chave')
                            ->valueLabel('valor')
                            ->helperText('Ex.: chave_pix, nome, banco, agencia, conta, doc etc.')
                            ->reorderable(),
                    ])
                    ->action(function (array $data, Provider $record) {
                        if (!$record->active) {
                            throw new \RuntimeException('Provider inativo.');
                        }
                        if (empty($record->provider_out)) {
                            throw new \RuntimeException('Alias OUT não configurado neste provider.');
                        }

                        $user = User::findOrFail($data['user_id']);

                        try {
                            // Verifica se o contrato OUT existe; se não, avisa.
                            if (!interface_exists(OutboundPaymentsProvider::class)) {
                                throw new \RuntimeException('OutboundPaymentsProvider não está disponível/implementado.');
                            }

                            // Instancia adapter OUT via mesma service_class,
                            // assumindo que a classe implementa também o contrato OUT
                            $service = app()->make($record->service_class, [
                                'config' => (array) $record->config ?: [],
                            ]);

                            if (!($service instanceof OutboundPaymentsProvider)) {
                                throw new \RuntimeException('Este provider não implementa operações OUT.');
                            }

                            $payload = [
                                'amount'       => (float) $data['amount'],
                                'external_id'  => $data['external_id'],
                                // Você pode padronizar campos como 'beneficiary' conforme seus adapters
                                'beneficiary'  => (array) ($data['beneficiary'] ?? []),
                            ];

                            $result = $service->createPayout($payload);

                            \Filament\Notifications\Notification::make()
                                ->title('OUT concluído')
                                ->body('Operação executada com sucesso.')
                                ->success()
                                ->send();

                            \Filament\Notifications\Notification::make()
                                ->title('Resposta')
                                ->body(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Falha no OUT')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            throw $e;
                        }
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit'   => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}
