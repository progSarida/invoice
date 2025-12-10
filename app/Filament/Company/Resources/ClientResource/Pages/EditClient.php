<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use App\Filament\Company\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
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
}
