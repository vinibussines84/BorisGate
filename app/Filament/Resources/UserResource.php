<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Usuários';
    protected static ?string $pluralLabel     = 'Usuários';
    protected static ?string $modelLabel      = 'Usuário';
    protected static ?int    $navigationSort  = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make()->schema([
                Forms\Components\Tabs::make()->tabs([
                    
                    /* ---------------------- PERFIL ---------------------- */
                    Forms\Components\Tabs\Tab::make('Perfil')->schema([
                        Forms\Components\Grid::make()->schema([
                            
                            Forms\Components\TextInput::make('nome_completo')
                                ->label('Nome completo')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\DatePicker::make('data_nascimento')
                                ->label('Data de nascimento')
                                ->native(false)
                                ->displayFormat('dd/MM/yyyy'),

                            Forms\Components\TextInput::make('email')
                                ->label('E-mail')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->prefixIcon('heroicon-o-envelope'),

                            Forms\Components\Select::make('user_status')
                                ->label('Status')
                                ->options([
                                    'ativo'     => 'Ativo',
                                    'inativo'   => 'Inativo',
                                    'bloqueado' => 'Bloqueado',
                                ])
                                ->required()
                                ->native(false),
                        ]),
                    ]),

                    /* ---------------------- ACESSO ---------------------- */
                    Forms\Components\Tabs\Tab::make('Acesso')->schema([
                        Forms\Components\Grid::make()->schema([

                            Forms\Components\Toggle::make('auto_approve_withdrawals')
                                ->label('Auto-aprovar saques criados pelo painel')
                                ->default(false)
                                ->onIcon('heroicon-o-bolt')
                                ->offIcon('heroicon-o-bolt-slash')
                                ->inline(false),

                            Forms\Components\Select::make('role')
                                ->label('Perfil/Cargo')
                                ->options([
                                    'admin'   => 'Admin',
                                    'manager' => 'Manager',
                                    'user'    => 'User',
                                ])
                                ->native(false)
                                ->searchable(),

                            Forms\Components\TagsInput::make('permissions')
                                ->label('Permissões (livres)')
                                ->placeholder('Digite e pressione Enter'),

                            Forms\Components\TextInput::make('password')
                                ->label('Senha')
                                ->password()
                                ->revealable()
                                ->minLength(8)
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn ($state) => filled($state))
                                ->required(fn (string $operation) => $operation === 'create')
                                ->suffixAction(
                                    Forms\Components\Actions\Action::make('gerar')
                                        ->label('Gerar')
                                        ->icon('heroicon-o-sparkles')
                                        ->action(fn ($set) => $set('password', Str::password(12)))
                                ),
                        ]),
                    ]),

                    /* ---------------------- FINANCEIRO ---------------------- */
                    Forms\Components\Tabs\Tab::make('Financeiro')->schema([
                        Forms\Components\TextInput::make('amount_available')->label('Saldo disponível')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('amount_retained')->label('Saldo retido')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('blocked_amount')->label('Bloqueado')->numeric()->prefix('R$'),
                    ]),

                    /* ---------------------- PROVIDERS ---------------------- */
                    Forms\Components\Tabs\Tab::make('Provider')->schema([
                        Forms\Components\Select::make('cashin_provider_id')
                            ->label('Provedor de Cash-in')
                            ->options(fn () => Provider::active()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->native(false),

                        Forms\Components\Select::make('cashout_provider_id')
                            ->label('Provedor de Cash-out')
                            ->options(fn () => Provider::active()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->native(false),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (User $r) => $r->email),

                Tables\Columns\BadgeColumn::make('user_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'ativo',
                        'warning' => 'inativo',
                        'danger'  => 'bloqueado',
                    ])
                    ->sortable(),

                /* ---------------------- TOGGLE NA LISTA ---------------------- */
                Tables\Columns\ToggleColumn::make('auto_approve_withdrawals')
                    ->label('Auto-aprova saque')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-bolt-slash')
                    ->alignCenter()
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        $record->auto_approve_withdrawals = $state ? 1 : 0;
                        $record->save();
                    }),

                Tables\Columns\TextColumn::make('cashinProvider.name')->label('Cash-in')->badge(),
                Tables\Columns\TextColumn::make('cashoutProvider.name')->label('Cash-out')->badge(),

                Tables\Columns\TextColumn::make('amount_available')->money('BRL', locale: 'pt_BR'),
                Tables\Columns\TextColumn::make('amount_retained')->money('BRL', locale: 'pt_BR'),
                Tables\Columns\TextColumn::make('blocked_amount')->money('BRL', locale: 'pt_BR'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
