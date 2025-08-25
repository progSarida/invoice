<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\SdiStatus;
use Filament\Actions;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Sectional;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Services\AndxorSoapService;
use Filament\Forms\Components\TextInput;

class EditNewInvoice extends EditRecord
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (Invoice $record) => $record->sdi_status == SdiStatus::DA_INVIARE),
            
            Actions\Action::make('duplica_fattura')
                ->label('Duplica Fattura')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Duplica Fattura')
                ->modalDescription('Vuoi creare una copia di questa fattura? La nuova fattura avrà un nuovo numero, una nuova data e gli importi delle voci a zero.')
                ->modalSubmitActionLabel('Duplica')
                ->action(function (Invoice $record) {
                    try {
                        $newInvoice = $record->replicate();                                 // creo una nuova istanza della fattura
                        
                        $newInvoice->sdi_status = SdiStatus::DA_INVIARE;                    // resetto i campi che devono essere unici o specifici della nuova fattura
                        $newInvoice->service_code = null;
                        $newInvoice->sdi_code = null;
                        $newInvoice->sdi_date = null;
                        
                        $newInvoice->year = now()->year;                                    // imposto anno corrente
                        $newInvoice->number = $newInvoice->calculateNextInvoiceNumber();    // genero il numero fattura
                        
                        $newInvoice->invoice_date = now()->format('Y-m-d');                 // imposto la data di oggi
                        
                        $newInvoice->save();                                                // salvo la nuova fattura (il boot method genererà automaticamente invoice_uid)
                        
                        $items = $record->invoiceItems->all();
                        $lastKey = array_key_last($items);

                        foreach ($items as $key => $item) {                                 // duplico gli InvoiceItem collegati
                            $newItem = $item->replicate();
                            $newItem->invoice_id = $newInvoice->id;
                            $newItem->quantity = 0;
                            $newItem->amount = 0;
                            $newItem->taxable = 0;
                            $newItem->total = 0;
                            $newItem->save();

                            if ($key === $lastKey) {
                                $newInvoice->updateTotal();                                 // aggiorno i totali della nuova fattura
                                $newInvoice->checkStampDuty();                              // verifico e inserisco eventuale imposta di bollo (non fa nulla)
                                $newItem->autoInsert();                                     // crea voci fattura di ritenute, riepiloghi e casse previdenziali
                            }
                        }
                        
                        Notification::make()
                            ->title('Fattura duplicata con successo')
                            ->body('Nuova fattura creata con numero: ' . $newInvoice->getNewInvoiceNumber())
                            ->success()
                            ->send();
                            
                        // Reindirizza alla nuova fattura
                        return redirect($this->getResource()::getUrl('edit', ['record' => $newInvoice]));
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore nella duplicazione')
                            ->body('Si è verificato un errore: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('stampa_pdf')
                ->label('Stampa PDF')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->action(function (Invoice $record) {
                    $vats = $record->vatResume();                                           // Creazione array con dati riepiloghi IVA
                    // dd($vats);
                    $funds = $record->getFundBreakdown();                                   // Creazione array con dati casse previdenziali
                    // dd($funds);
                    if(count($funds) > 0)
                        $vats = $record->updateResume($vats, $funds);                       // Aggiorna l'array con dati riepiloghi IVA con i dati delle casse previdenziali
                    // dd($vats);
                    $grouped = collect($vats)                                               // Raggruppamento dati riepilochi IVA in base a aliquota
                        ->groupBy('%')
                        ->where('auto', false)
                        ->map(function ($items, $percent) {
                            return [
                                '%' => $percent,
                                'taxable' => $items->sum('taxable'),
                                'vat' => $items->sum('vat'),
                                'total' => $items->sum('total'),
                                'norm' => $items->first()['norm'],
                                'free' => $items->first()['free'],
                            ];
                        })
                        ->values()
                        ->toArray();

                    $pdf = Pdf::loadView('pdf.invoice', [
                        'invoice' => $record,
                        'vats' => $grouped,
                        'funds' => $funds,
                    ]);

                    $pdf->setPaper('A4', 'portrait');

                    $pdf->setOptions(['margin-top' => 0]);

                    return response()->streamDownload(function () use ($pdf, $record) {
                        echo $pdf->output();
                    }, 'fattura-' . $record->printNumber() . '.pdf');
                }),

            Actions\Action::make('sendInvoice')
                ->label('Invia Fattura a SDI')
                ->action(function (Invoice $record, array $data) {
                    $items = $record->invoiceItems instanceof \Illuminate\Support\Collection
                        ? $record->invoiceItems->where('auto', false)
                        : $record->invoiceItems()->where('auto', false)->get();
                    if($items == null)
                        Notification::make()
                            ->title('Errore')
                            ->body('Impossibile inviare la fattura alla SdI. Voci fattura non presenti.')
                            ->warning()
                            ->send();
                    else{
                        $soapService = app(AndxorSoapService::class);
                        try {
                            $response = $soapService->sendInvoice($record, $data['password']);
                            // $response = $soapService->sendInvoice($record, 'W3iDWc3Q9w.3AUgd2zpz4');
                            Notification::make()
                                ->title('Fattura inviata con successo')
                                ->body('Progressivo: ' . $response->ProgressivoInvio)
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }
                })
                ->form([
                    TextInput::make('password')
                        ->label('Password SOAP')
                        ->password()
                        ->required(),
                ])
                ->requiresConfirmation(),

            Actions\Action::make('getStatus')
                ->label('Aggiorna status SDI')
                ->action(function (Invoice $record, array $data) {
                    $soapService = app(AndxorSoapService::class);
                    try {
                        $response = $soapService->updateStatus($record, $data['password']);
                        // $response = $soapService->sendInvoice($record, 'W3iDWc3Q9w.3AUgd2zpz4');
                        Notification::make()
                            ->title('Stato fattura aggiornato con successo')
                            // ->body('Progressivo: ' . $response->ProgressivoInvio)
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(function (Invoice $record) {
                    return $record->service_code != null;
                })
                ->form([
                    TextInput::make('password')
                        ->label('Password SOAP')
                        ->password()
                        ->required(),
                ])
                ->requiresConfirmation(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // $number = "";
        // for($i=strlen($data['number']);$i<3;$i++)
        // {
        //     $number.= "0";
        // }
        // $number = $number.$data['number'];
        // $data['invoice_uid'] = $number." / 0".$data['section']." / ".$data['year'];

        if($data['art_73']) {
            $number = "";
            // $date = $data['invoice_date'];
            $date = \Carbon\Carbon::parse($data['invoice_date'])->format('Y-m-d');
            for($i=strlen($data['number']);$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$data['number'];
            $data['invoice_uid'] = $number."/".$date;
        }
        else{
            $number = "";
            $sectional = Sectional::find($data['sectional_id'])->description;
            for($i=strlen($data['number']);$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$data['number'];
            $data['invoice_uid'] = $number."/".$sectional."/".$data['year'];
        }

        return $data;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
