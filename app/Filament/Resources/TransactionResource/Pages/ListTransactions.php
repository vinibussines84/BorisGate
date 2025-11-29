<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    /** Sem cards/widgets no topo */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    /** Sem ações no cabeçalho (já não cria, mas deixo vazio pra não aparecer nada extra) */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Opcional: sem widgets no rodapé também */
    protected function getFooterWidgets(): array
    {
        return [];
    }
}
