<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Pages;

use App\Filament\Company\Resources\PostalExpenseResource;
use App\Models\PostalExpense;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPostalExpense extends EditRecord
{
    protected static string $resource = PostalExpenseResource::class;

    protected function getHeaderActions(): array
    {
        $currentExpense = $this->record;
        // Precedente per act_date: data precedente O stessa data con ID minore
        $previousSBail = PostalExpense::where(function ($query) use ($currentExpense) {
                $query->where('act_date', '<', $currentExpense->act_date)
                    ->orWhere(function ($q) use ($currentExpense) {
                        $q->where('act_date', '=', $currentExpense->act_date)
                          ->where('id', '<', $currentExpense->id);
                    });
            })
            ->orderBy('act_date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per act_date: data successiva O stessa data con ID maggiore
        $nextSBail = PostalExpense::where(function ($query) use ($currentExpense) {
                $query->where('act_date', '>', $currentExpense->act_date)
                    ->orWhere(function ($q) use ($currentExpense) {
                        $q->where('act_date', '=', $currentExpense->act_date)
                          ->where('id', '>', $currentExpense->id);
                    });
            })
            ->orderBy('act_date', 'asc')->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('previous_expense')
                ->label('Atto prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(fn() => $previousSBail !== null)
                ->action(fn() => $this->redirect(PostalExpenseResource::getUrl('view', ['record' => $previousSBail->id]))),
            Actions\Action::make('next_expense')
                ->label('Atto succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn() => $nextSBail !== null)
                ->action(fn() => $this->redirect(PostalExpenseResource::getUrl('view', ['record' => $nextSBail->id]))),
            // Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
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
                ->modalHeading('Conferma eliminazione spesa di notifica')
                ->modalDescription('Sei sicuro di voler eliminare questa spesa di notifica? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/postal-expenses?')) {
                    return $this->previousUrl;
                }
                return PostalExpenseResource::getUrl('index');
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
