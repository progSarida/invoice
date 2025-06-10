<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewContracts extends ListRecords
{
    protected static string $resource = NewContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
