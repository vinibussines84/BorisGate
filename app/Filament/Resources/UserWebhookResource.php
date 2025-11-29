<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserWebhookResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class UserWebhookResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Fila & Webhook';
    protected static ?string $pluralLabel     = 'Fila & Webhook';
    protected static ?string $modelLabel      = 'Configuração de Webhook';
   // protected static ?string $navigationGroup = 'Administração';
    protected static ?int    $navigationSort  = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Usuário')
                ->description('Dados básicos (somente leitura).')
                ->schema([
                    Forms\Components\TextInput::make('nome_completo')
                        ->label('Nome')->disabled()->dehydrated(false)->columnSpan(6),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')->disabled()->dehydrated(false)->columnSpan(6),
                ])->columns(12),

            Forms\Components\Section::make('Webhook')
                ->description('Defina os endpoints que receberão eventos de IN (entradas) e OUT (saídas).')
                ->schema([
                    Forms\Components\Toggle::make('webhook_enabled')
                        ->label('Ativar Webhook')
                        ->helperText('Quando ativado, o sistema enviará notificações para os endpoints abaixo.')
                        ->live(),

                    Forms\Components\TextInput::make('webhook_in_url')
                        ->label('Endpoint de IN (cash-in)')
                        ->placeholder('https://seu-dominio.com/api/webhooks/in')
                        ->url()
                        ->maxLength(255)
                        ->required(fn (Get $get) => (bool) $get('webhook_enabled'))
                        ->disabled(fn (Get $get) => ! $get('webhook_enabled')),

                    Forms\Components\TextInput::make('webhook_out_url')
                        ->label('Endpoint de OUT (cash-out)')
                        ->placeholder('https://seu-dominio.com/api/webhooks/out')
                        ->url()
                        ->maxLength(255)
                        ->required(fn (Get $get) => (bool) $get('webhook_enabled'))
                        ->disabled(fn (Get $get) => ! $get('webhook_enabled')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Configurações de Webhook por usuário')
            ->searchPlaceholder('Buscar por nome ou e-mail…')
            ->columns([
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Usuário')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (User $record) => $record->email, position: 'below'),

                Tables\Columns\IconColumn::make('webhook_enabled')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('webhook_in_url')
                    ->label('IN')
                    ->limit(40)
                    ->tooltip(fn (User $record) => $record->webhook_in_url),

                Tables\Columns\TextColumn::make('webhook_out_url')
                    ->label('OUT')
                    ->limit(40)
                    ->tooltip(fn (User $record) => $record->webhook_out_url),

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
                Tables\Actions\EditAction::make()->label('Editar')->icon('heroicon-o-pencil-square'),
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
            'index' => Pages\ListUserWebhooks::route('/'),
            'edit'  => Pages\EditUserWebhook::route('/{record}/edit'),
        ];
    }
}
