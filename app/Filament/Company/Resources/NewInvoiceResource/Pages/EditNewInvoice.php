<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Filament\Company\Resources\NewInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewInvoice extends EditRecord
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
