<?php

namespace App\Filament\Company\Resources\InstrumentResource\Pages;

use App\Filament\Company\Resources\InstrumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInstrument extends ViewRecord
{
    protected static string $resource = InstrumentResource::class;

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
