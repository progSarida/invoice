<?php

namespace App\Filament\Company\Resources\ShipmentTypeResource\Pages;

use App\Filament\Company\Resources\ShipmentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShipmentTypes extends ListRecords
{
    protected static string $resource = ShipmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
