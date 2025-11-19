<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateNewActivePayments extends CreateRecord
{
    protected static string $resource = NewActivePaymentsResource::class;

    public function getTitle(): string
    {
        return "Nuovo pagamento";
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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

        if ($data['validated'] ?? false) {
            $data['validation_date'] = now();
            $data['validated_by_user_id'] = Auth::id();
        }

        return $data;
    }
}
