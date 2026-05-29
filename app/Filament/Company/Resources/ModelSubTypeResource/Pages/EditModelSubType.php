<?php

namespace App\Filament\Company\Resources\ModelSubTypeResource\Pages;

use App\Filament\Company\Resources\ModelSubTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditModelSubType extends EditRecord
{
    protected static string $resource = ModelSubTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
