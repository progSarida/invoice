<?php

namespace App\Filament\Company\Resources\ModelSubTypeResource\Pages;

use App\Filament\Company\Resources\ModelSubTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListModelSubTypes extends ListRecords
{
    protected static string $resource = ModelSubTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
