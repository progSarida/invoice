<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Pages;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassiveItemsRelationManager;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePassiveInvoice extends CreateRecord
{
    protected static string $resource = PassiveInvoiceResource::class;

    /**
     * Dopo la creazione porto l'utente dove può proseguire il lavoro: se può modificare
     * la fattura lo mando sulla scheda delle voci, altrimenti sulla sola consultazione.
     */
    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();
        $record = $this->getRecord();

        if ($resource::canEdit($record)) {
            $parameters = ['record' => $record];

            $manager = array_search(PassiveItemsRelationManager::class, $resource::getRelations(), true);
            if ($manager !== false) {                                   // apro direttamente la scheda delle voci fattura
                $parameters['activeRelationManager'] = $manager;
            }

            return $resource::getUrl('edit', $parameters);
        }

        if ($resource::canView($record)) {
            return $resource::getUrl('view', ['record' => $record]);
        }

        return $resource::getUrl('index');
    }
}
