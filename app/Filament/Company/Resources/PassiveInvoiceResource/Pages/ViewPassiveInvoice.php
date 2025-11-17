<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPassiveInvoice extends ViewRecord
{
    protected static string $resource = PassiveInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
