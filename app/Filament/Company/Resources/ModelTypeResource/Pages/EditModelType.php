<?php

namespace App\Filament\Company\Resources\ModelTypeResource\Pages;

use App\Filament\Company\Resources\ModelTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditModelType extends EditRecord
{
    protected static string $resource = ModelTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
