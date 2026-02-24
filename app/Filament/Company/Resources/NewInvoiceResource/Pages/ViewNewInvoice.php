<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use App\Filament\Company\Resources\InvoiceResource;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\DocType;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class ViewNewInvoice extends ViewRecord
{
    protected static string $resource = NewInvoiceResource::class;

    public function getTitle(): string | Htmlable
    {
        $number = $this->record->getNewInvoiceNumber();
        $doc = DocType::find($this->record->doc_type_id)->description;
        $date = Carbon::parse($this->record->invoice_date)->format('d/m/Y');

        // return $doc . " n. " . $number . " del " . $date;
        return "n.ro " . $number . " del " . $date;
    }

    protected function getHeaderActions(): array
    {
        $currentDoc = $this->record;
        // Precedente per ID: semplicemente ID minore
        $previousIDoc = Invoice::where('id', '<', $currentDoc->id)->orderBy('id', 'desc')->first();
        // Successivo per ID: semplicemente ID maggiore
        $nextIDoc = Invoice::where('id', '>', $currentDoc->id)->orderBy('id', 'asc')->first();
        // Precedente per invoice_date: data precedente O stessa data con ID minore
        $previousDDoc = Invoice::where(function ($query) use ($currentDoc) {
                $query->where('invoice_date', '<', $currentDoc->invoice_date)
                    ->orWhere(function ($q) use ($currentDoc) {
                        $q->where('invoice_date', '=', $currentDoc->invoice_date)
                        ->where('id', '<', $currentDoc->id);
                    });
            })
            ->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->first();
        // Successivo per invoice_date: data successiva O stessa data con ID maggiore
        $nextDDoc = Invoice::where(function ($query) use ($currentDoc) {
                $query->where('invoice_date', '>', $currentDoc->invoice_date)
                    ->orWhere(function ($q) use ($currentDoc) {
                        $q->where('invoice_date', '=', $currentDoc->invoice_date)
                        ->where('id', '>', $currentDoc->id);
                    });
            })
            ->orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->first();

        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento fatture
            Actions\Action::make('previous_d_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDoc) { return $previousDDoc;})
                ->action(function () use ($previousDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousDDoc->id]));
                }),
            Actions\Action::make('next_d_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDoc) { return $nextDDoc;})
                ->action(function () use ($nextDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextDDoc->id]));
                }),
            Actions\Action::make('previous_i_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDoc) { return $previousIDoc;})
                ->action(function () use ($previousIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $previousIDoc->id]));
                }),
            Actions\Action::make('next_i_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDoc) { return $nextIDoc;})
                ->action(function () use ($nextIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('view', ['record' => $nextIDoc->id]));
                }),
            Actions\ActionGroup::make([
                // Stampa fattura
                Actions\Action::make('stampa_pdf')
                    ->label('Stampa')
                    ->icon('heroicon-o-printer')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->requiresConfirmation()
                    ->modalHeading('Selezione stampa')
                    ->modalDescription('Seleziona il tipo di stampa per la fattura')
                    ->modalSubmitActionLabel('Selezione')
                    ->form([
                        Select::make('selection')->label('Tipo stampa')
                            ->options([
                                    "soft" => "Semplice",
                                    "hard" => "Strutturata"
                                ]
                            )
                            ->default('soft'),
                    ])
                    ->action(function (Invoice $record, $data) {
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
                            ->map(function ($items, $percent){
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

                        if($data['selection'] == "hard"){
                            $pdf = Pdf::loadView('pdf.invoice', [
                                'invoice' => $record,
                                'vats' => $grouped,
                                'funds' => $funds,
                            ]);
                        }
                        else{
                            $pdf = Pdf::loadView('pdf.invoice_alt', [
                                'invoice' => $record,
                                'vats' => $grouped,
                                'funds' => $funds,
                            ]);
                        }

                        $pdf->setPaper('A4', 'portrait');

                        $pdf->setOptions(['margin-top' => 0]);

                        return response()->streamDownload(function () use ($pdf, $record) {
                            echo $pdf->output();
                        }, 'fattura-' . $record->printNumber() . '.pdf');
                    }),
                Actions\Action::make('manage_reject')
                    ->label('Gestisci rifiuto')
                    ->icon('fluentui-mail-dismiss-20-o')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->visible(fn ($record) => $record->sdi_status == SdiStatus::RIFIUTATA)
                    ->requiresConfirmation()
                    ->modalHeading('Selezione tipo rifiuto')
                    ->modalDescription('Seleziona il tipo di rifiuto per il documento')
                    ->modalSubmitActionLabel('Conferma')
                    ->form([
                        Select::make('selection')
                            ->label('')
                            ->options(
                                collect(SdiStatus::cases())
                                    ->filter(fn (SdiStatus $status) => $status->showReject())
                                    ->mapWithKeys(fn ($status) => [
                                        $status->value => $status->getLabel() ?? $status->name
                                    ])
                            ),
                    ])
                    ->action(function (Invoice $record, $data) {
                        $record->update([
                            'sdi_status' => $data['selection'],
                        ]);

                        Notification::make()
                            ->title('Stato SDI aggiornato')
                            ->body('Lo stato SDI del documento ' . $record->getNewInvoiceNumber() . ' è stato aggiornato a ' . $record->sdi_status->getLabel())
                            ->icon('heroicon-o-check-circle')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('manage_discard')
                    ->label('Gestisci scarto')
                    ->icon('fluentui-mail-error-20-o')
                    ->color(Color::rgb('rgb(255, 0, 0)'))
                    ->visible(fn ($record) => $record->sdi_status == SdiStatus::SCARTATA)
                    ->requiresConfirmation()
                    ->modalHeading('Selezione tipo scarto')
                    ->modalDescription('Seleziona il tipo di scarto per il documento')
                    ->modalSubmitActionLabel('Conferma')
                    ->form([
                        Select::make('selection')
                            ->label('')
                            ->options(
                                collect(SdiStatus::cases())
                                    ->filter(fn (SdiStatus $status) => $status->showDiscard())
                                    ->mapWithKeys(fn ($status) => [
                                        $status->value => $status->getLabel() ?? $status->name
                                    ])
                            ),
                    ])
                    ->action(function (Invoice $record, $data) {
                        $record->update([
                            'sdi_status' => $data['selection'],
                        ]);

                        Notification::make()
                            ->title('Stato SDI aggiornato')
                            ->body('Lo stato SDI del documento ' . $record->getNewInvoiceNumber() . ' è stato aggiornato a ' . $record->sdi_status->getLabel())
                            ->icon('heroicon-o-check-circle')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('duplica_fattura')
                    ->authorize('create')
                    // ->hidden(fn(Invoice $record) => !is_null($record->parent_id))
                    ->label('Duplica')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->visible(fn($record) => $record->docType->name != 'TD00')
                    ->requiresConfirmation()
                    ->modalHeading('Duplica Fattura')
                    ->modalDescription('Vuoi creare una copia di questa fattura? La nuova fattura avrà un nuovo numero e una nuova data.')
                    ->modalSubmitActionLabel('Duplica')->form([

                    ])
                    ->action(function (Get $get, Set $set, Invoice $record, array $data) {
                        try {
                            DB::beginTransaction();

                            $newInvoice = $record->replicate();                                             // creo una nuova istanza della fattura

                            $newInvoice->contract_detail_id = $newInvoice->contract->lastDetail->id;        // metto l'id del dettaglio contratto in vigore

                            $newInvoice->parent_id = null;                                                  // resetto la fattura stornata (in caso di nota di credito)

                            $newInvoice->year = now()->year;                                                // imposto anno corrente
                            $newInvoice->number = $newInvoice->calculateNextInvoiceNumber();                // genero il numero fattura

                            $newInvoice->invoice_date = now()->format('Y-m-d');                             // imposto la data di oggi

                            $newInvoice->budget_year = now()->year;                                         // imposto anno corrente
                            $newInvoice->accrual_year = now()->year;                                        // imposto anno corrente

                            $newInvoice->invoice_reference = null;                                          // resetto i campi del riferimento (unici per fattura)
                            $newInvoice->reference_date_from = null;
                            $newInvoice->reference_date_to = null;
                            $newInvoice->reference_number_from = null;
                            $newInvoice->reference_number_to = null;
                            $newInvoice->total_number = null;

                            $newInvoice->description = static::generateDescriptionFromModel($newInvoice);   // creo la nuova descrizione
                            $newInvoice->free_description = null;

                            $newInvoice->payment_status = PaymentStatus::WAITING;                           // imposto lo stato pagamento a 'In attesa'
                            $newInvoice->last_payment_date = null;                                          // resetto la data dell'ultimo pagamento

                            $newInvoice->total_payment = 0.0;                                               // imposto a zero il totale dei pagamenti della fattura
                            $newInvoice->total_notes = 0.0;                                                 // imposto a zero il totale delle note di credito della fattura

                            $newInvoice->sdi_status = SdiStatus::DA_INVIARE;                                // resetto i campi dello sdi (unici per fattura)
                            $newInvoice->service_code = null;
                            $newInvoice->sdi_code = null;
                            $newInvoice->sdi_date = null;
                            $newInvoice->pdf_path = null;
                            $newInvoice->xml_path = null;

                            if(!$data['duplicate_amounts']){
                                $newInvoice->total = 0.0;
                                $newInvoice->no_vat_total = 0.0;
                            }

                            $newInvoice->save();                                                            // salvo la nuova fattura
                                                                                                            // (il boot method genererà automaticamente invoice_uid)

                            if ($data['duplicate_items']) {
                                $items = $record->invoiceItems->all();
                                $lastKey = array_key_last($items);

                                foreach ($items as $key => $item) {                                         // duplico gli InvoiceItem collegati
                                    // if(($item->vat_code_type && $item->vat_code_type !== 'vc06a') && !$item->postal_expense_id){
                                    if($item->invoice_element_id){                                          // se non è imposta di bollo, riepilogo, o spesa di notifica
                                        $newItem = $item->replicate();
                                        $newItem->invoice_id = $newInvoice->id;
                                        // $newItem->invoice_element_id = $item->invoice_element_id ?? null;   // tolgo perchè replicate copia già i dati
                                        // $newItem->description = $item->description ?? null;
                                        // $newItem->transaction_type = $item->transaction_type ?? null;
                                        $newItem->start_date = null;
                                        $newItem->end_date = null;
                                        $newItem->code = $item->code ?? null;
                                        $newItem->quantity = $data['duplicate_amounts'] ? $item->quantity : null;
                                        $newItem->measure_unit = $data['duplicate_amounts'] ? $item->measure_unit : null;
                                        $newItem->unit_price = $data['duplicate_amounts'] ? $item->unit_price : null;
                                        $newItem->amount = $data['duplicate_amounts'] ? $item->amount : 0.00;
                                        $newItem->taxable = $data['duplicate_amounts'] ? $item->taxable : 0.00;
                                        $newItem->total = $data['duplicate_amounts'] ? $item->total : 0.00;
                                        // $newItem->vat_code_type = $item->vat_code_type ?? null;             // tolgo perchè replicate copia già i dati
                                        // $newItem->auto = $item->auto ?? null;
                                        // $newItem->is_with_vat = $item->is_with_vat ?? null;
                                        $newItem->save();

                                        // $newInvoice->invoiceCheckStampDuty();                               // verifico e inserisco eventuale imposta di bollo (non fa nulla)

                                        $newItem->calculateTotal();
                                        $newItem->save();
                                        $newItem->checkStampDuty();                                         // operazioni per inserimenti bollo e riepiloghi
                                        $newItem->autoInsert();

                                    }
                                    // if ($key === $lastKey) {
                                    //     $newInvoice->updateTotal();                                         // aggiorno i totali della nuova fattura
                                    //     $newInvoice->invoiceCheckStampDuty();                               // verifico e inserisco eventuale imposta di bollo (non fa nulla)
                                    //     $newItem->autoInsert();                                             // crea voci fattura di ritenute, riepiloghi e casse previdenziali
                                    //     $newInvoice->updateTotal();                                         // aggiorno i totali della nuova fattura
                                    // }
                                }
                            } else {
                                // Se non si duplicano le voci, aggiorno comunque i totali e verifico l'imposta di bollo
                                // $newInvoice->invoiceCheckStampDuty();
                                // $newInvoice->updateTotal();
                            }

                            DB::commit();

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
                Actions\EditAction::make()
                    ->hidden(fn($record) => $record->sdi_status != SdiStatus::DA_INVIARE                                // posso modificare se non è stata inviata
                                            && $record->sdi_status != SdiStatus::RIFIUTATA                              // posso modificare se è stata rifiutata
                                            && $record->sdi_status != SdiStatus::SCARTATA),                             // posso modificare se è stata scartata
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
