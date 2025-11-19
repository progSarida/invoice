<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\NewContractResource;

class CreateNewContract extends CreateRecord
{
    protected static string $resource = NewContractResource::class;

    public function getTitle(): string
    {
        return "Nuovo contratto";
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Redirect alla pagina "edit" direttamente nella relazione desiderata
        $this->redirect(
            NewContractResource::getUrl('edit', ['record' => $record])
        );
    }

    protected function beforeSave(): void
    {
        if (!is_numeric($this->form->getState()['amount'])) {
            Notification::make()
                ->title('Importo non valido')
                ->danger()
                ->send();

            $this->halt(); // blocca il salvataggio
        }
    }
}
