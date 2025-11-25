<?php

namespace App\Filament\Company\Resources\PassivePaymentResource\Pages;

use App\Filament\Company\Resources\PassivePaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPassivePayment extends ViewRecord
{
    protected static string $resource = PassivePaymentResource::class;

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
