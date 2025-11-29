<?php

namespace App\Filament\Resources\UserTaxResource\Pages;

use App\Filament\Resources\UserTaxResource;
use Filament\Resources\Pages\EditRecord;

class EditUserTax extends EditRecord
{
    protected static string $resource = UserTaxResource::class;

    protected function getHeaderActions(): array
    {
        // sem DeleteAction
        return [];
    }

    /**
     * Usa os helpers da própria página (compatível com várias versões do Filament).
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Salvar'),
            $this->getCancelFormAction()->label('Cancelar'),
        ];
    }
}
