<?php

namespace App\Filament\Company\Resources\ActTypeResource\Pages;

use App\Filament\Company\Resources\ActTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewActType extends ViewRecord
{
    protected static string $resource = ActTypeResource::class;

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
