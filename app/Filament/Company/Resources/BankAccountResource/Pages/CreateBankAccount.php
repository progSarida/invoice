<?php

namespace App\Filament\Company\Resources\BankAccountResource\Pages;

use Filament\Actions;
use App\Models\BankAccount;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\BankAccountResource;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $exists = BankAccount::where('number', $data['number'])->exists();
        if ($exists) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un conto bancario con questo numero di conto.')
                ->warning()
                ->send();
            $this->halt(); // opzionale: blocca la UI se serve
        }
        $exists = BankAccount::where('iban', $data['iban'])->exists();
        if ($exists) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un conto bancario con questo IBAN.')
                ->warning()
                ->send();
            $this->halt();
        }
        $exists = BankAccount::where('bic', $data['bic'])->exists();
        if ($exists) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un conto bancario con questo BIC.')
                ->warning()
                ->send();
            $this->halt();
        }
        $exists = BankAccount::where('swift', $data['swift'])->exists();
        if ($exists) {
            Notification::make()
                ->title('Attenzione')
                ->body('Esiste già un conto bancario con questo SWIFT.')
                ->warning()
                ->send();
            $this->halt();
        }
        return parent::handleRecordCreation($data);
    }
}
