<?php

namespace App\Filament\Company\Resources\ActTypeResource\Pages;

use App\Filament\Company\Resources\ActTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActTypes extends ListRecords
{
    protected static string $resource = ActTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
