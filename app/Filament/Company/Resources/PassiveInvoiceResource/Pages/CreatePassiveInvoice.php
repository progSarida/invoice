<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePassiveInvoice extends CreateRecord
{
    protected static string $resource = PassiveInvoiceResource::class;
    protected static bool $canCreateAnother = false;                                            // Elimina il pulsante 'Salva & nuovo'

    /**
     * Dopo la creazione porto l'utente dove può proseguire il lavoro: se può modificare
     * la fattura lo mando in modifica, altrimenti sulla sola consultazione.
     */
    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();
        $record = $this->getRecord();

        if ($resource::canEdit($record)) {
            return $resource::getUrl('edit', ['record' => $record]);
        }

        if ($resource::canView($record)) {
            return $resource::getUrl('view', ['record' => $record]);
        }

        return $resource::getUrl('index');
    }
}
