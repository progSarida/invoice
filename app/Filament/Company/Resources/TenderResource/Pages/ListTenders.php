<?php

namespace App\Filament\Company\Resources\TenderResource\Pages;

use App\Filament\Company\Resources\TenderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenders extends ListRecords
{
    protected static string $resource = TenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
