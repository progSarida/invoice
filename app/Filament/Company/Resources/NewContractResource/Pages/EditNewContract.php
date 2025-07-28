<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Company\Resources\NewContractResource;

class EditNewContract extends EditRecord
{
    protected static string $resource = NewContractResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

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
