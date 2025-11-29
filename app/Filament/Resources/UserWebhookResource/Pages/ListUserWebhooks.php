<?php

namespace App\Filament\Resources\UserWebhookResource\Pages;

use App\Filament\Resources\UserWebhookResource;
use Filament\Resources\Pages\ListRecords;

class ListUserWebhooks extends ListRecords
{
    protected static string $resource = UserWebhookResource::class;

    protected function getHeaderActions(): array
    {
        // sem CreateAction — 1 usuário = 1 registro na própria tabela users
        return [];
    }
}
