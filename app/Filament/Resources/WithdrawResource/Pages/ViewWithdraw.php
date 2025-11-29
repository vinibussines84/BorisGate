<?php

namespace App\Filament\Resources\WithdrawResource\Pages;

use App\Filament\Resources\WithdrawResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWithdraw extends ViewRecord
{
    protected static string $resource = WithdrawResource::class;

    protected function getHeaderActions(): array
    {
        return []; // leitura
    }
}
