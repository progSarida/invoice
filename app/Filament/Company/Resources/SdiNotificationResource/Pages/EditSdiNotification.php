<?php

namespace App\Filament\Company\Resources\SdiNotificationResource\Pages;

use App\Filament\Company\Resources\SdiNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSdiNotification extends EditRecord
{
    protected static string $resource = SdiNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
