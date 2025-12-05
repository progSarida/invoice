<?php

namespace App\Filament\Company\Resources\PiValidationResource\Pages;

use App\Filament\Company\Resources\PiValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPiValidations extends ListRecords
{
    protected static string $resource = PiValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
