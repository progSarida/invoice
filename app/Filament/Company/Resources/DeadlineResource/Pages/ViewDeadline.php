<?php

namespace App\Filament\Company\Resources\DeadlineResource\Pages;

use App\Filament\Company\Resources\DeadlineResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeadline extends ViewRecord
{
    protected static string $resource = DeadlineResource::class;

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
