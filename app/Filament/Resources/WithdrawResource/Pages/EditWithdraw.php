<?php

namespace App\Filament\Resources\WithdrawResource\Pages;

use App\Filament\Resources\WithdrawResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWithdraw extends EditRecord
{
    protected static string $resource = WithdrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
