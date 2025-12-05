<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use App\Models\ActivePayments;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditNewActivePayments extends EditRecord
{
    protected static string $resource = NewActivePaymentsResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $invoice = Invoice::find($data['invoice_id']);
        $paymentDate = $data['payment_date'];

        if ($paymentDate && $invoice && $paymentDate < $invoice->invoice_date) {
            Notification::make()
                ->title('Attenzione! La data del pagamento è inferiore alla data della fattura.')
                ->danger()
                ->duration(6000)
                ->send();

            throw new Halt();
        }

        if ($data['validated'] && !$this->record->validated) {
            $data['validation_date'] = now();
            $data['validated_by_user_id'] = Auth::id();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $currentPayment = $this->record;
        $previousDPayment = ActivePayments::where('payment_date', '<=', $currentPayment->payment_date)
                ->where('id', '!=', $currentPayment->id)->orderBy('payment_date', 'desc')->orderBy('id', 'desc')->first();
        $nextDPayment = ActivePayments::where('payment_date', '>=', $currentPayment->payment_date)
                ->where('id', '!=', $currentPayment->id)->orderBy('payment_date', 'asc')->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('previous_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDPayment) { return $previousDPayment;})
                ->action(function () use ($previousDPayment) {
                    $this->redirect(NewActivePaymentsResource::getUrl('view', ['record' => $previousDPayment->id]));
                }),
            Actions\Action::make('next_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDPayment) { return $nextDPayment;})
                ->action(function () use ($nextDPayment) {
                    $this->redirect(NewActivePaymentsResource::getUrl('view', ['record' => $nextDPayment->id]));
                }),
            // Actions\DeleteAction::make()
            //     ->visible(fn (): bool => Auth::user()->isManager())
            //     ->disabled(fn () => $this->record->validated),
            // Actions\ForceDeleteAction::make()
            //     ->visible(fn (): bool => Auth::user()->isManager())
            //     ->disabled(fn () => $this->record->validated),
            // Actions\RestoreAction::make()
            //     ->visible(fn (): bool => Auth::user()->isManager())
            //     ->disabled(fn () => $this->record->validated),
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
                if ($this->previousUrl && str($this->previousUrl)->contains('/new-active-payments?')) {
                    return $this->previousUrl;
                }
                return NewActivePaymentsResource::getUrl('index');
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
