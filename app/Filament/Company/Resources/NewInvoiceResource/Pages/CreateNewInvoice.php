<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use Carbon\Carbon;
use Filament\Actions;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\NewInvoiceResource;

class CreateNewInvoice extends CreateRecord
{
    protected static string $resource = NewInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ( $data['timing_type'] === 'contestuale' && Carbon::parse($data['invoice_date'])->lt(now()->subDays(9) )) {      // controllo 9 giorni indietro data fattura contestuale
            Notification::make()
                ->body('La data della fattura non può essere più vecchia di 9 giorni.')
                ->danger()
                ->duration(5000)
                ->send();

            $this->halt();

            return $data;
        }

        if ( $data['timing_type'] === 'differita' && !empty($data['delivery_date']) ) {                                     // controllo 15 giorni mese successivo data fattura differita
            $deliveryDate = Carbon::parse($data['delivery_date']);
            $cutoff = $deliveryDate->copy()->addMonth()->day(15);

            if (now()->gt($cutoff)) {
                Notification::make()
                    ->body('Non puoi creare una fattura differita oltre il 15 del mese successivo alla data del DDT.')
                    ->danger()
                    ->duration(5000)
                    ->send();

                $this->halt();

                return $data;
            }
        }

        $last = Invoice::where('year',$data['year'])->where('sectional_id',$data['sectional_id'])->orderBy('number', 'desc')->first();
        if ($last && Carbon::parse($data['invoice_date'])->lt($last->invoice_date)) {                                       // controllo consistenza date fatture dello stesso sezionario
            Notification::make()
                // ->title('Data non valida')
                ->body('La data della nuova fattura non può essere anteriore a quella dell’ultima emessa per lo stesso sezionario.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['flow'] = 'out';

        return $data;
    }
}
