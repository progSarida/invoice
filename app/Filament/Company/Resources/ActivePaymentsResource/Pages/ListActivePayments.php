<?php

namespace App\Filament\Company\Resources\ActivePaymentsResource\Pages;

use App\Filament\Company\Resources\ActivePaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivePayments extends ListRecords
{
    protected static string $resource = ActivePaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
