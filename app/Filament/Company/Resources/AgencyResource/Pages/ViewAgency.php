<?php

namespace App\Filament\Company\Resources\AgencyResource\Pages;

use App\Filament\Company\Resources\AgencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

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
