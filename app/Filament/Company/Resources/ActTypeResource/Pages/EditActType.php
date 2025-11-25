<?php

namespace App\Filament\Company\Resources\ActTypeResource\Pages;

use App\Filament\Company\Resources\ActTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditActType extends EditRecord
{
    protected static string $resource = ActTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->color('success'),
            $this->getCancelFormAction(),
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
                ->modalHeading('Conferma eliminazione tipo atto')
                ->modalDescription('Sei sicuro di voler eliminare questo tipo di atto? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/act-types?')) {
                    return $this->previousUrl;
                }
                return ActTypeResource::getUrl('index');
            });
    }
}
