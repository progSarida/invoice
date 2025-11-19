<?php

namespace App\Filament\Company\Resources\InvoiceItemResource\Pages;

use App\Filament\Company\Resources\InvoiceItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceItems extends ListRecords
{
    protected static string $resource = InvoiceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
