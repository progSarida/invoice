<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewActivePayments extends ListRecords
{
    protected static string $resource = NewActivePaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
