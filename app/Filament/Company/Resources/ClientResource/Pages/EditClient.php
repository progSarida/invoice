<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use App\Filament\Company\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->denomination;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        $currentClient = $this->record;
        $previousAClient = Client::where('denomination', '<', $currentClient->denomination)->orderBy('denomination', 'desc')->first();
        $nextAClient = Client::where('denomination', '>', $currentClient->denomination)->orderBy('denomination', 'asc')->first();
        $previousIClient = Client::where('id', '<', $currentClient->id)->orderBy('id', 'desc')->first();
        $nextIClient = Client::where('id', '>', $currentClient->id)->orderBy('id', 'asc')->first();
        return [
            // Scorrimento alfabetico
            Actions\Action::make('previous_a_client')
                ->label('Alfabetico prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousAClient) { return $previousAClient;})
                ->action(function () use ($previousAClient) {
                    $this->redirect(ClientResource::getUrl('edit', ['record' => $previousAClient->id]));
                }),
            Actions\Action::make('next_a_client')
                ->label('Alfabetico succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextAClient) { return $nextAClient;})
                ->action(function () use ($nextAClient) {
                    $this->redirect(ClientResource::getUrl('edit', ['record' => $nextAClient->id]));
                }),
            // Scorrimento alfabetico
            Actions\Action::make('previous_i_client')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIClient) { return $previousIClient;})
                ->action(function () use ($previousIClient) {
                    $this->redirect(ClientResource::getUrl('edit', ['record' => $previousIClient->id]));
                }),
            Actions\Action::make('next_i_client')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIClient) { return $nextIClient;})
                ->action(function () use ($nextIClient) {
                    $this->redirect(ClientResource::getUrl('edit', ['record' => $nextIClient->id]));
                }),
            // Actions\DeleteAction::make()
            //     ->keyBindings(['f4'])
            //     ->visible(fn (): bool => Auth::user()->isManager()),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            $this->getDeleteFormAction()
                ->extraAttributes([
                    'class' => ' md:ml-auto md:w-auto ',
                ]),
        ];
    }

    protected function getDeleteFormAction()
    {
        return Actions\DeleteAction::make('delete')
                ->requiresConfirmation()
                ->modalHeading('Conferma eliminazione cliente')
                ->modalDescription('Sei sicuro di voler eliminare questo cliente? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/clients?')) {
                    return $this->previousUrl;
                }
                return ClientResource::getUrl('index');
            });
    }

    protected function getResetFormAction(): Actions\Action
    {
        return Actions\Action::make('reset')
            ->label('Annulla')
            ->color('gray')
            ->action(function () {
                $this->data = $this->getRecord()->toArray();
                $this->fillForm();
            });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $existsDenomination = false;
        $existsCF = false;
        $existsPI = false;
        $tenantId = Filament::getTenant()->id;
        if($data['denomination'])
            $existsDenomination = Client::where('denomination', $data['denomination'])
                                        ->where('company_id', $tenantId)
                                        ->where('id', '!=', $record->id)
                                        ->exists();
        if ($data['subtype'] === 'man' || $data['subtype'] === 'woman')
            $existsCF = Client::where('tax_code', $data['tax_code'])
                            ->where('company_id', $tenantId)
                            ->where('id', '!=', $record->id)
                            ->exists();
        else
            $existsPI = Client::orWhere('vat_code', $data['vat_code'])
                            ->where('company_id', $tenantId)
                            ->where('id', '!=', $record->id)
                            ->exists();
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
        return parent::handleRecordUpdate($record, $data);
    }
}
