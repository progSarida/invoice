<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use Carbon\Carbon;
use Filament\Actions;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\NewContract;

class CreateNewInvoice extends CreateRecord
{
    protected static string $resource = NewInvoiceResource::class;

    public function getTitle(): string
    {
        return "Nuova fattura";
    }

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

        $data['contract_detail_id'] = NewContract::find($data['contract_id'])?->lastDetail?->id;
        $data['flow'] = 'out';

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['company_id'] = filament()->getTenant()?->id;
        $record = Invoice::create($data);

        // GESTIONE VOCI NOTE DI CREDITO IN CASO DI LIMITE TEMPORALE PASSATO (1 anno)
        if($record->year_limit == 'si'){                                                                                    // nota soggetta a limite temporale
            // copio le voci della fattura da stornare ma applico vat_code_type 'vc00'
            $items = InvoiceItem::where('invoice_id', $record->parent_id)->get();
            foreach($items as $item){
                // creo un InvoiceItem con invoice_id == id nota creata e vat_code_type == 'vc00'
                InvoiceItem::create([
                    'invoice_id'            => $record->id,
                    'invoice_element_id'    => $item->invoice_element_id,
                    'description'           => $item->description,
                    'amount'                => $item->amount,
                    'total'                 => $item->amount,
                    'vat_code_type'         => 'vc00',
                    'is_with_vat'           => true
                ]);
            }
        }
        else if($record->year_limit == 'no'){                                                                               // nota non soggetta a limite temporale
            // copio le voci della fattura da stornare
            $items = InvoiceItem::where('invoice_id', $record->parent_id)->get();
            // controllo item parent?
            foreach($items as $item){
                // creo un InvoiceItem con invoice_id == id nota creata
                InvoiceItem::create([
                    'invoice_id'            => $record->id,
                    'invoice_element_id'    => $item->invoice_element_id,
                    'description'           => $item->description,
                    'amount'                => $item->amount,
                    'total'                 => $item->total,
                    'vat_code_type'         => $item->vat_code_type,
                    'is_with_vat'           => $item->is_with_vat
                ]);
            }
        }

        return $record;
    }
}
