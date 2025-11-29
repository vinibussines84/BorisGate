<?php

namespace App\Filament\Resources\UserWebhookResource\Pages;

use App\Filament\Resources\UserWebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserWebhook extends CreateRecord
{
    protected static string $resource = UserWebhookResource::class;
}
