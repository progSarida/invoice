<?php

namespace App\Filament\Resources\SenderResource\Pages;

use App\Filament\Resources\SenderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSender extends ViewRecord
{
    protected static string $resource = SenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
