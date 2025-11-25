<?php

namespace App\Filament\Company\Resources\ContractDetailResource\Pages;

use App\Filament\Company\Resources\ContractDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContractDetails extends ListRecords
{
    protected static string $resource = ContractDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
