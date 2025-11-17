<?php

namespace App\Filament\Company\Resources\InvoiceItemResource\Pages;

use App\Filament\Company\Resources\InvoiceItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceItem extends CreateRecord
{
    protected static string $resource = InvoiceItemResource::class;
}
