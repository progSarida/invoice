<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
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
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => Auth::user()->isManagerOf(\Filament\Facades\Filament::getTenant()))
                ->disabled(fn () => $this->record->validated),
            Actions\ForceDeleteAction::make()
                ->visible(fn (): bool => Auth::user()->isManagerOf(\Filament\Facades\Filament::getTenant()))
                ->disabled(fn () => $this->record->validated),
            Actions\RestoreAction::make()
                ->visible(fn (): bool => Auth::user()->isManagerOf(\Filament\Facades\Filament::getTenant()))
                ->disabled(fn () => $this->record->validated),
        ];
    }
}
