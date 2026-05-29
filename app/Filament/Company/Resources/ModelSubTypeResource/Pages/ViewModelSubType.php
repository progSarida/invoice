<?php

namespace App\Filament\Company\Resources\ModelSubTypeResource\Pages;

use App\Filament\Company\Resources\ModelSubTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewModelSubType extends ViewRecord
{
    protected static string $resource = ModelSubTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
