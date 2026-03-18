<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use Filament\Actions;
use App\Models\Client;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\ClientResource;
use Filament\Facades\Filament;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        return "Nuovo cliente";
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $existsDenomination = false;
        $existsCF = false;
        $existsPI = false;
        $tenantId = Filament::getTenant()->id;
        if($data['denomination'])
            $existsDenomination = Client::where('denomination', $data['denomination'])
                                        ->where('company_id', $tenantId)->exists();
        if ($data['subtype'] === 'man' || $data['subtype'] === 'woman')
            $existsCF = Client::where('tax_code', $data['tax_code'])
                            ->where('company_id', $tenantId)->exists();
        else
            $existsPI = Client::orWhere('vat_code', $data['vat_code'])
                            ->where('company_id', $tenantId)->exists();
        if($existsDenomination) {
            if (in_array($data['subtype'], ['man', 'woman', 'professional'])) {
                $field = 'cognome e nome';
            }
            else {
                $field = 'denominazione';
            }
            $client = Client::where('denomination', $data['denomination'])->first();
            if($client) {
                Notification::make()
                    ->title("Attenzione! Esiste già un cliente con {$field} {$data['denomination']}")
                    ->danger()
                    ->send();
            }
            $this->halt(); // opzionale: blocca la UI se serve
        }
        if ($existsCF) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un cliente con questo codice fiscale.')
                ->warning()
                ->send();
            $this->halt(); // opzionale: blocca la UI se serve
        }
        else if ($existsPI) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un cliente con questa partita IVA.')
                ->warning()
                ->send();
            $this->halt(); // opzionale: blocca la UI se serve
        }
        return parent::handleRecordCreation($data);
    }
}
