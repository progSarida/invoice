<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNewActivePayments extends ViewRecord
{
    protected static string $resource = NewActivePaymentsResource::class;

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
