<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Filament\Company\Resources\NewInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewInvoices extends ListRecords
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
