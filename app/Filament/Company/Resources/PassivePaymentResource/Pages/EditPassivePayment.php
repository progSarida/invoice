<?php

namespace App\Filament\Company\Resources\PassivePaymentResource\Pages;

use App\Filament\Company\Resources\PassivePaymentResource;
use App\Models\PassivePayment;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPassivePayment extends EditRecord
{
    protected static string $resource = PassivePaymentResource::class;

    protected function getHeaderActions(): array
    {
        $currentPayment = $this->record;
        $previousDPayment = PassivePayment::where('payment_date', '<=', $currentPayment->payment_date)
                ->where('id', '!=', $currentPayment->id)->orderBy('payment_date', 'desc')->orderBy('id', 'desc')->first();
        $nextDPayment = PassivePayment::where('payment_date', '>=', $currentPayment->payment_date)
                ->where('id', '!=', $currentPayment->id)->orderBy('payment_date', 'asc')->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('previous_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDPayment) { return $previousDPayment;})
                ->action(function () use ($previousDPayment) {
                    $this->redirect(PassivePaymentResource::getUrl('view', ['record' => $previousDPayment->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDPayment) { return $nextDPayment;})
                ->action(function () use ($nextDPayment) {
                    $this->redirect(PassivePaymentResource::getUrl('view', ['record' => $nextDPayment->id]));
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
                ->modalHeading('Conferma eliminazione pagamento')
                ->modalDescription('Sei sicuro di voler eliminare questo pagamento? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/passive-payments?')) {
                    return $this->previousUrl;
                }
                return PassivePaymentResource::getUrl('index');
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
