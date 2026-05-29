<?php

namespace App\Filament\Company\Resources\ModelTypeResource\Pages;

use App\Filament\Company\Resources\ModelTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewModelType extends ViewRecord
{
    protected static string $resource = ModelTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
