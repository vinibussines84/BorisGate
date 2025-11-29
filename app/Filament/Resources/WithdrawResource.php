<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawResource\Pages;
use App\Models\User;
use App\Models\Withdraw;
use App\Services\TrustOut\TrustOutService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

class WithdrawResource extends Resource
{
    protected static ?string $model = Withdraw::class;

    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Saques';
    protected static ?string $modelLabel       = 'Saque';
    protected static ?string $pluralModelLabel = 'Saques';
    protected static ?string $navigationGroup  = 'Financeiro';
    protected static ?int    $navigationSort   = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Usuário')
                ->relationship(name: 'user', titleAttribute: 'nome_completo')
                ->getOptionLabelFromRecordUsing(fn (User $u) => $u->nome_completo ?: $u->email)
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('amount')
                ->label('Valor líquido (R$)')
                ->numeric()
                ->prefix('R$')
                ->required(),

            Forms\Components\TextInput::make('fee_amount')
                ->label('Taxa (R$)')
                ->prefix('R$')
                ->numeric()
                ->visible(fn (): bool => Schema::hasColumn('withdraws', 'fee_amount')),

            Forms\Components\TextInput::make('gross_amount')
                ->label('Valor bruto (R$)')
                ->prefix('R$')
                ->numeric()
                ->visible(fn (): bool => Schema::hasColumn('withdraws', 'gross_amount')),

            Forms\Components\TextInput::make('pixkey')
                ->label('Chave Pix')
                ->required(),

            Forms\Components\Select::make('pixkey_type')
                ->label('Tipo de chave')
                ->options([
                    'evp'   => 'EVP',
                    'phone' => 'Telefone',
                    'cpf'   => 'CPF',
                    'cnpj'  => 'CNPJ',
                    'email' => 'E-mail',
                ])
                ->required(),

            Forms\Components\TextInput::make('description')
                ->label('Descrição'),

            Forms\Components\TextInput::make('idempotency_key')
                ->label('Idempotency Key')
                ->required()
                ->maxLength(100),

            Forms\Components\Select::make('status')
                ->label('Status (definido pelo webhook)')
                ->options([
                    'paid'       => 'Paid',
                    'pending'    => 'Pendente',
                    'failed'     => 'Falha',
                    'error'      => 'Erro',
                    'processing' => 'Processando',
                    'canceled'   => 'Cancelado',
                ])
                ->disabled()
                ->dehydrated(false),

            Forms\Components\DateTimePicker::make('processed_at')
                ->label('Processado em')
                ->disabled()
                ->dehydrated(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $withdrawsTable = (new Withdraw())->getTable(); // 'withdraws'
        $usersTable     = (new User())->getTable();     // 'users'

        return $table
            // ⚠️ Sem prefixo aqui — evita json_extract() indevido
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user.nome_completo')
                    ->label('Usuário')
                    ->formatStateUsing(fn ($state, Withdraw $rec) => $rec->user?->nome_completo ?: $rec->user?->email ?: '—')
                    ->searchable(query: function (Builder $query, string $search) use ($usersTable) {
                        $query->whereHas('user', function ($q) use ($search, $usersTable) {
                            $q->where("{$usersTable}.nome_completo", 'like', "%{$search}%")
                              ->orWhere("{$usersTable}.email", 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction) use ($withdrawsTable, $usersTable) {
                        // ordena pelo nome do usuário e volta a selecionar somente as colunas de withdraws
                        $query->leftJoin($usersTable, "{$usersTable}.id", '=', "{$withdrawsTable}.user_id")
                              ->orderBy("{$usersTable}.nome_completo", $direction)
                              ->select("{$withdrawsTable}.*");
                    }),

                Tables\Columns\IconColumn::make('user_auto_approve')
                    ->label('Auto-aprova')
                    ->icon(fn (Withdraw $rec) => ($rec->user?->auto_approve_withdrawals ?? false)
                        ? 'heroicon-o-bolt' : 'heroicon-o-bolt-slash')
                    ->color(fn (Withdraw $rec) => ($rec->user?->auto_approve_withdrawals ?? false) ? 'success' : 'gray')
                    ->tooltip(fn (Withdraw $rec) => ($rec->user?->auto_approve_withdrawals ?? false) ? 'Ativo' : 'Inativo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor (R$)')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Number::currency((float) ($state ?? 0), 'BRL', locale: 'pt_BR'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('fee_amount')
                    ->label('Taxa (R$)')
                    ->visible(fn (): bool => Schema::hasColumn('withdraws', 'fee_amount'))
                    ->formatStateUsing(fn ($state) => Number::currency((float) ($state ?? 0), 'BRL', locale: 'pt_BR'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Bruto (R$)')
                    ->visible(fn (): bool => Schema::hasColumn('withdraws', 'gross_amount'))
                    ->formatStateUsing(fn ($state) => Number::currency((float) ($state ?? 0), 'BRL', locale: 'pt_BR'))
                    ->alignRight(),

                Tables\Columns\TextColumn::make('pixkey')
                    ->label('Chave Pix')
                    ->searchable()
                    ->formatStateUsing(function ($state, Withdraw $record) {
                        $v = (string) $state;
                        if (!$v) return '—';
                        $type = $record->pixkey_type;
                        return match ($type) {
                            'cpf'   => substr($v, 0, 3) . '.***.***-' . substr($v, -2),
                            'cnpj'  => substr($v, 0, 2) . '.***.***/****-' . substr($v, -2),
                            'email' => (function ($e) {
                                [$u, $d] = array_pad(explode('@', $e, 2), 2, '');
                                $u2 = strlen($u) > 2 ? substr($u, 0, 2) . '***' : $u . '***';
                                return $u2 . '@' . $d;
                            })($v),
                            'phone' => '(**) *****-' . substr(preg_replace('/\D+/', '', $v), -4),
                            'evp'   => substr($v, 0, 4) . '****' . substr($v, -4),
                            default => '••••',
                        };
                    }),

                Tables\Columns\TextColumn::make('pixkey_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid'       => 'success',
                        'pending'    => 'warning',
                        'failed', 'error' => 'danger',
                        'processing' => 'info',
                        'canceled'   => 'gray',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'paid'       => 'Paid',
                        'pending'    => 'Pendente',
                        'failed'     => 'Falha',
                        'error'      => 'Erro',
                        'processing' => 'Processando',
                        'canceled'   => 'Cancelado',
                        default      => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processado em')
                    ->dateTime('dd/MM/yyyy HH:mm')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('dd/MM/yyyy HH:mm')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('dd/MM/yyyy HH:mm')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid'       => 'Paid',
                        'pending'    => 'Pendente',
                        'failed'     => 'Falha',
                        'error'      => 'Erro',
                        'processing' => 'Processando',
                        'canceled'   => 'Cancelado',
                    ]),

                Tables\Filters\TernaryFilter::make('user_auto_approve_withdrawals')
                    ->label('Auto-aprovação (usuário)')
                    ->placeholder('Todos')
                    ->trueLabel('Somente com auto-aprovação')
                    ->falseLabel('Somente sem auto-aprovação')
                    ->indicateUsing(function ($state): ?string {
                        $v = is_array($state) ? ($state['value'] ?? null) : $state;
                        return $v === true ? 'Auto-aprovação: Sim' : ($v === false ? 'Auto-aprovação: Não' : null);
                    })
                    ->queries(
                        true:  fn ($q) => $q->whereHas('user', fn ($u) => $u->where('auto_approve_withdrawals', true)),
                        false: fn ($q) => $q->whereHas('user', fn ($u) => $u->where('auto_approve_withdrawals', false)),
                        blank: fn ($q) => $q
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Enviar para processamento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Withdraw $record) => $record->status === 'pending')
                    ->action(function (Withdraw $record): void {
                        try {
                            if (Schema::hasColumn($record->getTable(), 'meta')) {
                                $meta = (array) ($record->meta ?? []);
                                $meta['manual_sent'] = true;
                                $record->meta = $meta;
                                $record->save();
                            }

                            if (class_exists(TrustOutService::class)) {
                                /** @var TrustOutService $svc */
                                $svc = app(TrustOutService::class);

                                $user   = $record->user;
                                $pixEnum = strtoupper(match ($record->pixkey_type) {
                                    'cpf'   => 'CPF',
                                    'cnpj'  => 'CNPJ',
                                    'email' => 'EMAIL',
                                    'phone' => 'PHONE',
                                    default => 'EVP',
                                });

                                // documento fixo (alinhado ao controller)
                                $doc = preg_replace('/\D+/', '', static::trustOutFixedDocument());
                                $externalId = $record->idempotency_key ?? ('withdraw_'.$record->id);

                                $resp = $svc->createWithdrawal([
                                    'externalId'     => $externalId,
                                    'pixKey'         => (string) $record->pixkey,
                                    'pixKeyType'     => $pixEnum,
                                    'documentNumber' => $doc,
                                    'name'           => (string) ($user->name ?? 'Cliente ClosedPay'),
                                    'amount'         => (float) $record->amount,
                                ]);

                                $data   = (array) ($resp['data'] ?? []);
                                $provId = (string) ($data['id'] ?? '');
                                $status = strtolower((string) ($data['status'] ?? ($resp['status'] ?? '')));

                                $map = [
                                    'pending'    => 'pending',
                                    'processing' => 'processing',
                                    'paid'       => 'paid',
                                    'completed'  => 'paid',
                                    'failed'     => 'failed',
                                    'error'      => 'failed',
                                    'canceled'   => 'canceled',
                                ];

                                if (Schema::hasColumn($record->getTable(), 'provider_reference') && $provId !== '') {
                                    $record->provider_reference = $provId;
                                }
                                if (Schema::hasColumn($record->getTable(), 'provider')) {
                                    $record->provider = 'trustout';
                                }
                                if (Schema::hasColumn($record->getTable(), 'provider_message') && isset($resp['message'])) {
                                    $record->provider_message = mb_strimwidth((string) $resp['message'], 0, 250, '…');
                                }
                                if (Schema::hasColumn($record->getTable(), 'meta')) {
                                    $meta                  = (array) ($record->meta ?? []);
                                    $meta['external_id']   = $externalId;
                                    $meta['provider_echo'] = $resp;
                                    $record->meta          = $meta;
                                }

                                $record->status = $map[$status] ?? 'pending';
                                $record->save();
                            }

                            Notification::make()
                                ->title('Solicitação enviada.')
                                ->body('O status será atualizado pelo webhook do provedor.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            $record->status = 'pending';
                            if (Schema::hasColumn($record->getTable(), 'meta')) {
                                $meta = (array) ($record->meta ?? []);
                                $meta['manual_sent_error'] = $e->getMessage();
                                $record->meta = $meta;
                            }
                            $record->save();

                            Notification::make()
                                ->title('Falha ao encaminhar saque ao provedor.')
                                ->body('Verifique credenciais/serviço e tente novamente.')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar saque')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar saque')
                    ->modalDescription('Ao cancelar, o valor BRUTO será estornado para a carteira do usuário. Confirmar?')
                    ->visible(fn (Withdraw $record) => in_array($record->status, ['pending', 'processing'], true))
                    ->action(function (Withdraw $record): void {
                        try {
                            DB::transaction(function () use ($record) {
                                $w = Withdraw::whereKey($record->id)->lockForUpdate()->firstOrFail();
                                if (in_array($w->status, ['paid', 'canceled'], true)) {
                                    return;
                                }

                                $u = $w->user()->lockForUpdate()->firstOrFail();

                                $gross = null;
                                if (Schema::hasColumn('withdraws', 'gross_amount') && !is_null($w->gross_amount)) {
                                    $gross = (float) $w->gross_amount;
                                } elseif (Schema::hasColumn('withdraws', 'fee_amount') && !is_null($w->fee_amount)) {
                                    $gross = (float) $w->amount + (float) $w->fee_amount;
                                }

                                if ($gross === null) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        'withdraw' => 'Não foi possível estornar: gross_amount/fee_amount não registrados.',
                                    ]);
                                }

                                $u->amount_available = round(($u->amount_available ?? 0) + round($gross, 2), 2);
                                $u->save();

                                $w->status = 'canceled';
                                if (Schema::hasColumn('withdraws', 'canceled_at')) {
                                    $w->canceled_at = now();
                                }
                                $w->save();
                            });

                            Notification::make()
                                ->title('Saque cancelado e valor BRUTO estornado.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            $body = 'Tente novamente ou verifique os dados do saque.';
                            if ($e instanceof \Illuminate\Validation\ValidationException) {
                                $body = collect($e->errors())->flatten()->implode(' ');
                            }

                            Notification::make()
                                ->title('Falha ao cancelar o saque.')
                                ->body($body)
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Withdraw $record) => static::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkApprove')
                        ->label('Enviar selecionados para processamento')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $ok = 0; $fail = 0; $skip = 0;

                            foreach ($records as $w) {
                                if ($w->status !== 'pending') { $skip++; continue; }

                                try {
                                    if (Schema::hasColumn($w->getTable(), 'meta')) {
                                        $meta = (array) ($w->meta ?? []);
                                        $meta['manual_sent'] = true;
                                        $w->meta = $meta;
                                        $w->save();
                                    }

                                    if (class_exists(TrustOutService::class)) {
                                        /** @var TrustOutService $svc */
                                        $svc = app(TrustOutService::class);

                                        $user    = $w->user;
                                        $pixEnum = strtoupper(match ($w->pixkey_type) {
                                            'cpf'   => 'CPF',
                                            'cnpj'  => 'CNPJ',
                                            'email' => 'EMAIL',
                                            'phone' => 'PHONE',
                                            default => 'EVP',
                                        });

                                        $doc = preg_replace('/\D+/', '', static::trustOutFixedDocument());
                                        $externalId = $w->idempotency_key ?? ('withdraw_'.$w->id);

                                        $resp = $svc->createWithdrawal([
                                            'externalId'     => $externalId,
                                            'pixKey'         => (string) $w->pixkey,
                                            'pixKeyType'     => $pixEnum,
                                            'documentNumber' => $doc,
                                            'name'           => (string) ($user->name ?? 'Cliente ClosedPay'),
                                            'amount'         => (float) $w->amount,
                                        ]);

                                        $data   = (array) ($resp['data'] ?? []);
                                        $provId = (string) ($data['id'] ?? '');
                                        $status = strtolower((string) ($data['status'] ?? ($resp['status'] ?? '')));

                                        $map = [
                                            'pending'    => 'pending',
                                            'processing' => 'processing',
                                            'paid'       => 'paid',
                                            'completed'  => 'paid',
                                            'failed'     => 'failed',
                                            'error'      => 'failed',
                                            'canceled'   => 'canceled',
                                        ];

                                        if (Schema::hasColumn($w->getTable(), 'provider_reference') && $provId !== '') {
                                            $w->provider_reference = $provId;
                                        }
                                        if (Schema::hasColumn($w->getTable(), 'provider')) {
                                            $w->provider = 'trustout';
                                        }
                                        if (Schema::hasColumn($w->getTable(), 'provider_message') && isset($resp['message'])) {
                                            $w->provider_message = mb_strimwidth((string) $resp['message'], 0, 250, '…');
                                        }
                                        if (Schema::hasColumn($w->getTable(), 'meta')) {
                                            $meta                  = (array) ($w->meta ?? []);
                                            $meta['external_id']   = $externalId;
                                            $meta['provider_echo'] = $resp;
                                            $w->meta               = $meta;
                                        }

                                        $w->status = $map[$status] ?? 'pending';
                                        $w->save();
                                    }

                                    $ok++;
                                } catch (\Throwable $e) {
                                    report($e);

                                    $w->status = 'pending';
                                    if (Schema::hasColumn($w->getTable(), 'meta')) {
                                        $meta = (array) ($w->meta ?? []);
                                        $meta['manual_sent_error'] = $e->getMessage();
                                        $w->meta = $meta;
                                    }
                                    $w->save();

                                    $fail++;
                                }
                            }

                            Notification::make()
                                ->title('Processamento em lote concluído')
                                ->body("Enviados: {$ok}. Falhas: {$fail}. Ignorados: {$skip}.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('bulkCancel')
                        ->label('Cancelar selecionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            DB::transaction(function () use ($records) {
                                $records->each(function (Withdraw $w) {
                                    $rw = Withdraw::whereKey($w->id)->lockForUpdate()->first();
                                    if (!$rw || in_array($rw->status, ['paid', 'canceled'], true)) {
                                        return;
                                    }

                                    $u = $rw->user()->lockForUpdate()->first();
                                    if (!$u) return;

                                    $gross = null;
                                    if (Schema::hasColumn('withdraws', 'gross_amount') && !is_null($rw->gross_amount)) {
                                        $gross = (float) $rw->gross_amount;
                                    } elseif (Schema::hasColumn('withdraws', 'fee_amount') && !is_null($rw->fee_amount)) {
                                        $gross = (float) $rw->amount + (float) $rw->fee_amount;
                                    }
                                    if ($gross === null) return;

                                    $u->amount_available = round(($u->amount_available ?? 0) + round($gross, 2), 2);
                                    $u->save();

                                    $rw->status = 'canceled';
                                    if (Schema::hasColumn('withdraws', 'canceled_at')) {
                                        $rw->canceled_at = now();
                                    }
                                    $rw->save();
                                });
                            });

                            Notification::make()
                                ->title('Saques cancelados e estornados.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWithdraws::route('/'),
            'create' => Pages\CreateWithdraw::route('/create'),
            'edit'   => Pages\EditWithdraw::route('/{record}/edit'),
        ];
    }

    private static function trustOutFixedDocument(): string
    {
        return '28343827007';
    }
}
