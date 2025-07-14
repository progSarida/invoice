<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use Filament\Actions;
use App\Models\Client;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\ClientResource;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $exists = Client::where('tax_code', $data['tax_code'])
            ->orWhere('vat_code', $data['vat_code'])
            ->exists();
        if ($exists) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un cliente con questo codice fiscale o partita IVA.')
                ->warning()
                ->send();
            $this->halt(); // opzionale: blocca la UI se serve
        }
        return parent::handleRecordCreation($data);
    }
}
