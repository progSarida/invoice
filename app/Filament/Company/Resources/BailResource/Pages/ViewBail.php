<?php

namespace App\Filament\Company\Resources\BailResource\Pages;

use App\Filament\Company\Resources\BailResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBail extends ViewRecord
{
    protected static string $resource = BailResource::class;

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
