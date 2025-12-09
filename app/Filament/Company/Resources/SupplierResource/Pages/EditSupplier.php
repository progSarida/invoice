<?php

namespace App\Filament\Company\Resources\SupplierResource\Pages;

use App\Filament\Company\Resources\SupplierResource;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        $currentSupplier = $this->record;
        $previousASupplier = Supplier::where('denomination', '<', $currentSupplier->denomination)->orderBy('denomination', 'desc')->first();
        $nextASupplier = Supplier::where('denomination', '>', $currentSupplier->denomination)->orderBy('denomination', 'asc')->first();
        $previousISupplier = Supplier::where('id', '<', $currentSupplier->id)->orderBy('id', 'desc')->first();
        $nextISupplier = Supplier::where('id', '>', $currentSupplier->id)->orderBy('id', 'asc')->first();
        return [
            // Scorrimento alfabetico
            Actions\Action::make('previous_a_supplier')
                ->label('Alfabetico prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousASupplier) { return $previousASupplier;})
                ->action(function () use ($previousASupplier) {
                    $this->redirect(SupplierResource::getUrl('edit', ['record' => $previousASupplier->id]));
                }),
            Actions\Action::make('next_a_supplier')
                ->label('Alfabetico succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextASupplier) { return $nextASupplier;})
                ->action(function () use ($nextASupplier) {
                    $this->redirect(SupplierResource::getUrl('edit', ['record' => $nextASupplier->id]));
                }),
            // Scorrimento id
            Actions\Action::make('previous_i_supplier')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousISupplier) { return $previousISupplier;})
                ->action(function () use ($previousISupplier) {
                    $this->redirect(SupplierResource::getUrl('edit', ['record' => $previousISupplier->id]));
                }),
            Actions\Action::make('next_i_supplier')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextISupplier) { return $nextISupplier;})
                ->action(function () use ($nextISupplier) {
                    $this->redirect(SupplierResource::getUrl('edit', ['record' => $nextISupplier->id]));
                }),
            // Actions\DeleteAction::make()
            //     ->visible(fn (): bool => Auth::user()->isManager()),
        ];
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
                ->modalHeading('Conferma eliminazione fornitore')
                ->modalDescription('Sei sicuro di voler eliminare questo fornitore? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/suppliers?')) {
                    return $this->previousUrl;
                }
                return SupplierResource::getUrl('index');
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
