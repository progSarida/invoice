<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Filament\Company\Resources\NewInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNewInvoice extends CreateRecord
{
    protected static string $resource = NewInvoiceResource::class;
}
