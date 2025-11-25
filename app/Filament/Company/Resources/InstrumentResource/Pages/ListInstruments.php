<?php

namespace App\Filament\Company\Resources\InstrumentResource\Pages;

use App\Filament\Company\Resources\InstrumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstruments extends ListRecords
{
    protected static string $resource = InstrumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
