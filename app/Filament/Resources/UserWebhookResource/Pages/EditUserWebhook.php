<?php

namespace App\Filament\Resources\UserWebhookResource\Pages;

use App\Filament\Resources\UserWebhookResource;
use Filament\Resources\Pages\EditRecord;

class EditUserWebhook extends EditRecord
{
    protected static string $resource = UserWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        // Helpers nativos (compatível com várias versões do Filament)
        return [
            $this->getSaveFormAction()->label('Salvar'),
            $this->getCancelFormAction()->label('Cancelar'),
        ];
    }
}
