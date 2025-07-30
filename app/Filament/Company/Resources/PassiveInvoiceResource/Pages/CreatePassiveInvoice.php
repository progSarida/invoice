<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePassiveInvoice extends CreateRecord
{
    protected static string $resource = PassiveInvoiceResource::class;
}
