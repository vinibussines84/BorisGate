<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Illuminate\Support\Number;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return []; // somente visual
    }

    protected function getFormSchema(): array
    {
        return [
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
        ];
    }
}
