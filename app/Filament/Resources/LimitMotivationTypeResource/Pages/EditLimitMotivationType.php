<?php

namespace App\Filament\Resources\LimitMotivationTypeResource\Pages;

use App\Filament\Resources\LimitMotivationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLimitMotivationType extends EditRecord
{
    protected static string $resource = LimitMotivationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
