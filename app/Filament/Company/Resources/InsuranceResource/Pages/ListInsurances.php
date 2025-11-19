<?php

namespace App\Filament\Company\Resources\InsuranceResource\Pages;

use App\Filament\Company\Resources\InsuranceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInsurances extends ListRecords
{
    protected static string $resource = InsuranceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
