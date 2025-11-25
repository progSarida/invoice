<?php

namespace App\Filament\Company\Resources\BankAccountResource\Pages;

use App\Filament\Company\Resources\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBankAccount extends ViewRecord
{
    protected static string $resource = BankAccountResource::class;

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
