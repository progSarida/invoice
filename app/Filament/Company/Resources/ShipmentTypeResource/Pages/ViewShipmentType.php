<?php

namespace App\Filament\Company\Resources\ShipmentTypeResource\Pages;

use App\Filament\Company\Resources\ShipmentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShipmentType extends ViewRecord
{
    protected static string $resource = ShipmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\EditAction::make(),
        ];
    }
}
