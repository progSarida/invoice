<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\ClientSubType;
use App\Enums\PaymentStatus;
use App\Enums\ReversalGroupType;
use App\Enums\SdiStatus;
use Filament\Actions;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Sectional;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\DocType;
use App\Models\ReversalMotivationType;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use ZipArchive;

class EditNewInvoice extends EditRecord
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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
            // Actions\DeleteAction::make()
            //     ->visible(fn (Invoice $record) => $record->sdi_status == SdiStatus::DA_INVIARE),

            // Scorrimento fatture
            Actions\Action::make('previous_d_doc')
                ->label('Data prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDDoc) { return $previousDDoc;})
                ->action(function () use ($previousDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('edit', ['record' => $previousDDoc->id]));
                }),
            Actions\Action::make('next_d_doc')
                ->label('Data succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDDoc) { return $nextDDoc;})
                ->action(function () use ($nextDDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('edit', ['record' => $nextDDoc->id]));
                }),
            Actions\Action::make('previous_i_doc')
                ->label('Id prec.')
                ->color('gray')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIDoc) { return $previousIDoc;})
                ->action(function () use ($previousIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('edit', ['record' => $previousIDoc->id]));
                }),
            Actions\Action::make('next_i_doc')
                ->label('Id succ.')
                ->color('gray')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIDoc) { return $nextIDoc;})
                ->action(function () use ($nextIDoc) {
                    $this->redirect(NewInvoiceResource::getUrl('edit', ['record' => $nextIDoc->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('duplica_fattura')
                    ->authorize('create')
                    ->hidden(fn(Invoice $record) => !is_null($record->parent_id))
                    ->label('Duplica')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    // ->visible(fn($record) => $record->docType->name != 'TD00')
                    ->requiresConfirmation()
                    ->modalHeading('Duplica Fattura')
                    ->modalDescription('Vuoi creare una copia di questa fattura? La nuova fattura avrà un nuovo numero e una nuova data.')
                    ->modalSubmitActionLabel('Duplica')->form([
                        Checkbox::make('duplicate_items')
                            ->label('Duplica anche le voci della fattura')
                            ->live()
                            ->default(true),
                        Checkbox::make('duplicate_amounts')
                            ->label('Duplica anche gli importi')
                            ->visible(fn (Get $get) => $get('duplicate_items'))
                            ->default(false),
                    ])
                    ->action(function (Get $get, Set $set, Invoice $record, array $data) {
                        try {
                            DB::beginTransaction();

                            $newInvoice = $record->replicate();                                             // creo una nuova istanza della fattura
Log::info('Fattura replicata');
                            $newInvoice->contract_detail_id = $newInvoice->contract?->lastDetail?->id;      // metto l'id del dettaglio contratto in vigore
Log::info('ID dettaglio contratto');
                            $newInvoice->parent_id = null;                                                  // resetto la fattura stornata (in caso di nota di credito)
Log::info('Resetto parent_id');
                            $newInvoice->year = now()->year;                                                // imposto anno corrente
                            $newInvoice->number = $newInvoice->calculateNextInvoiceNumber();                // genero il numero fattura

                            $newInvoice->invoice_date = now()->format('Y-m-d');                             // imposto la data di oggi
Log::info('Aggiornati numero, anno e data fattura');
                            $newInvoice->budget_year = now()->year;                                         // imposto anno corrente
                            $newInvoice->accrual_year = now()->year;                                        // imposto anno corrente
Log::info('Reset anni bilancio e gestione');
                            $newInvoice->invoice_reference = null;                                          // resetto i campi del riferimento (unici per fattura)
                            $newInvoice->reference_date_from = null;
                            $newInvoice->reference_date_to = null;
                            $newInvoice->reference_number_from = null;
                            $newInvoice->reference_number_to = null;
                            $newInvoice->total_number = null;
Log::info('Reset riferimenti');
                            $newInvoice->description = static::generateDescriptionFromModel($newInvoice);   // creo la nuova descrizione
                            $newInvoice->free_description = null;
Log::info('Reset descrizione');
                            $newInvoice->payment_status = PaymentStatus::WAITING;                           // imposto lo stato pagamento a 'In attesa'
                            $newInvoice->last_payment_date = null;                                          // resetto la data dell'ultimo pagamento
Log::info('Reset dati pagamento');
                            $newInvoice->total_payment = 0.0;                                               // imposto a zero il totale dei pagamenti della fattura
                            $newInvoice->total_notes = 0.0;                                                 // imposto a zero il totale delle note di credito della fattura
Log::info('Reset totali associati');
                            $newInvoice->sdi_status = SdiStatus::DA_INVIARE;                                // resetto i campi dello sdi (unici per fattura)
                            $newInvoice->service_code = null;
                            $newInvoice->sdi_code = null;
                            $newInvoice->sdi_date = null;
                            $newInvoice->pdf_path = null;
                            $newInvoice->xml_path = null;
Log::info('Reset dati SDI');
                            if(!$data['duplicate_amounts']){
                                $newInvoice->total = 0.0;
                                $newInvoice->no_vat_total = 0.0;
                            }
Log::info('Reset totali');
                            $newInvoice->save();                                                            // salvo la nuova fattura
                                                                                                            // (il boot method genererà automaticamente invoice_uid)
Log::info('Salvataggio');
                            if ($data['duplicate_items']) {
                                $items = $record->invoiceItems->all();
                                $lastKey = array_key_last($items);
Log::info('Inizio copia voci');
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
Log::info('Commit');
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

                Actions\Action::make('converti_in_fattura')
                    ->hidden(fn(Invoice $record) => !is_null($record->parent_id))
                    ->label('Converti in fattura')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->visible(fn($record) => $record->docType->name == 'TD00')
                    ->requiresConfirmation()
                    ->modalHeading('Converti in Fattura')
                    ->modalDescription('Vuoi convertire il preavviso di fattura in una fattura?')
                    ->modalSubmitActionLabel('Converti')
                    ->action(function (Invoice $record) {
                        try {
                            DB::beginTransaction();

                            $record->doc_type_id = DocType::where('name', 'TD01')->first()->id;
                            $record->number = $record->calculateNextInvoiceNumber();
                            $record->invoice_date = today();
                            $record->sdi_status = SdiStatus::DA_INVIARE;

                            DB::commit();

                            $record->save();                                                        // salvo la fattura (il boot method genererà automaticamente invoice_uid)

                            Notification::make()
                                ->title('Fattura convertita con successo')
                                ->body('Nuova fattura creata con numero: ' . $record->getNewInvoiceNumber())
                                ->success()
                                ->send();

                            // Reindirizza alla nuova fattura
                            return redirect($this->getResource()::getUrl('edit', ['record' => $record]));

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore nella duplicazione')
                                ->body('Si è verificato un errore: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

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

                // Actions\Action::make('sendInvoice')
                //     ->label('Invia a SDI')
                //     ->icon('tabler-send')
                //     ->visible(function($record) {
                //         $prec = Invoice::where('year', $record->year)
                //                     ->where('sectional_id', $record->sectional_id)
                //                     ->where('number', '<', $record->number)
                //                     ->with(['client'])
                //                     ->whereHas('client', function ($q) {
                //                         $q->whereNotIn('subtype', [ClientSubType::MAN, ClientSubType::WOMAN]);
                //                     })
                //                     ->orderBy('number', 'desc')
                //                     ->first();
                //         $precOk = $prec ? $prec->sdi_status != SdiStatus::DA_INVIARE : true;
                //         $noForewarning = $record->docType->name != 'TD00';
                //         return $precOk && $noForewarning;
                //     })
                //     ->action(function (Invoice $record, array $data) {
                //         $items = $record->invoiceItems instanceof \Illuminate\Support\Collection
                //             ? $record->invoiceItems->where('auto', false)
                //             : $record->invoiceItems()->where('auto', false)->get();
                //         if($items == null)
                //             Notification::make()
                //                 ->title('Errore')
                //                 ->body('Impossibile inviare la fattura alla SdI. Voci fattura non presenti.')
                //                 ->warning()
                //                 ->send();
                //         else{
                //             $soapService = app(AndxorSoapService::class);
                //             try {
                //                 $response = $soapService->sendInvoice($record, $data['password']);
                //                 // $response = $soapService->sendInvoice($record, 'W3iDWc3Q9w.3AUgd2zpz4');
                //                 Notification::make()
                //                     ->title('Fattura inviata con successo')
                //                     ->body('Progressivo: ' . $response->ProgressivoInvio)
                //                     ->success()
                //                     ->send();
                //             } catch (\Exception $e) {
                //                 Notification::make()
                //                     ->title('Errore')
                //                     ->body($e->getMessage())
                //                     ->danger()
                //                     ->duration(10000)
                //                     ->send();
                //             }
                //         }
                //     })
                //     ->disabled(function (Invoice $record) {
                //         // return $record->service_code != null;
                //         return $record->sdi_status->lockSend();
                //     })
                //     ->form([
                //         TextInput::make('password')
                //             ->label('Password SOAP')
                //             ->password()
                //             ->revealable()
                //             ->required(),
                //     ])
                //     ->requiresConfirmation(),

                Actions\Action::make('sendInvoice')
                    ->label('Invia a SDI')
                    ->icon('tabler-send')
                    ->visible(function($record) {
                        $prec = Invoice::where('year', $record->year)
                                    ->where('sectional_id', $record->sectional_id)
                                    ->where('number', '<', $record->number)
                                    ->with(['client'])
                                    ->whereHas('client', function ($q) {
                                        $q->whereNotIn('subtype', [ClientSubType::MAN, ClientSubType::WOMAN]);
                                    })
                                    ->orderBy('number', 'desc')
                                    ->first();
                        $precOk = $prec ? $prec->sdi_status != SdiStatus::DA_INVIARE : true;
                        $noForewarning = $record->docType->name != 'TD00';
                        return $precOk && $noForewarning;
                    })
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
                            // Dispatch del job in background
                            \App\Jobs\SendInvoiceToSdiJob::dispatch(
                                $record,
                                $data['password'],
                                auth()->id()
                            );

                            // Dispatch del job per invio email PDF se presente indirizzo di cortesia
                            if ($record->contract?->courtesy_address && $record->company->sender !== null) {
                                \App\Jobs\SendInvoicePdfEmailJob::dispatch(
                                    $record,
                                    $record->contract->courtesy_address,
                                    auth()->id()
                                );
                            }

                            Notification::make()
                                ->title('Invio in elaborazione')
                                ->body("La fattura {$record->getNewInvoiceNumber()} è stata messa in coda per l'invio allo SDI. Riceverai una notifica al termine.")
                                ->info()
                                ->send();
                        }
                    })
                    ->disabled(function (Invoice $record) {
                        return $record->sdi_status->lockSend();
                    })
                    ->form([
                        Placeholder::make('courtesy')
                            ->label('')
                            ->visible(fn($record) => $record->contract?->courtesy_address && $record->company->sender == null)
                            // ->content('Attenzione non è possivbile inviare la copia di cortesia perchè non sono stati inseriti i parametri dell amail di invio.')
                            ->content(new HtmlString('
                                <div style="background-color: #fbe36e; border-color: #dac524;" class="p-4 rounded-lg bg-warning-500/10 border border-warning-500">
                                    <span class="text-warning-600 font-bold">
                                        ⚠️ Attenzione:
                                    </span>
                                    <span class="text-warning-700">
                                        la copia di cortesia non sarà inviata perché non sono stati inseriti i parametri dell\'email di invio.
                                    </span>
                                </div>
                            ')),
                        TextInput::make('password')
                            ->label('Password SOAP')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->requiresConfirmation(),

                // Actions\Action::make('getStatus')
                //     ->label('Aggiorna stato SDI')
                //     ->icon('tabler-refresh')
                //     ->visible(fn($record) => $record->sdi_status != SdiStatus::DA_INVIARE && $record->docType->name != 'TD00')
                //     ->action(function (Invoice $record, array $data) {
                //         $soapService = app(AndxorSoapService::class);
                //         try {
                //             $response = $soapService->updateStatus($record, $data['password']);

                //             SdiRequest::create([
                //                 'company_id' => \Filament\Facades\Filament::getTenant()->id,
                //                 'request_date' => today()->format('Y-m-d'),
                //                 'sdi_request_type' => 'single',
                //                 'invoice_id' => $record->id
                //             ]);

                //             Notification::make()
                //                 ->title('Stato fattura aggiornato con successo')
                //                 // ->body('Progressivo: ' . $response->ProgressivoInvio)
                //                 ->success()
                //                 ->send();
                //         } catch (\Exception $e) {
                //             Notification::make()
                //                 ->title('Errore')
                //                 ->body($e->getMessage())
                //                 ->danger()
                //                 ->send();
                //         }
                //     })
                //     ->disabled(function (Invoice $record) {
                //         // return $record->service_code != null;
                //         return $record->sdi_status->lockUpdate();
                //     })
                //     ->form([
                //         TextInput::make('password')
                //             ->label('Password SOAP')
                //             ->password()
                //             ->revealable()
                //             ->required(),
                //     ])
                //     ->requiresConfirmation(),

                Actions\Action::make('getStatus')
                    ->label('Aggiorna stato SDI')
                    ->icon('tabler-refresh')
                    ->visible(fn($record) => $record->sdi_status != SdiStatus::DA_INVIARE && $record->docType->name != 'TD00')
                    ->action(function (Invoice $record, array $data) {
                        // Dispatch del job in background
                        \App\Jobs\UpdateInvoiceSdiStatusJob::dispatch(
                            $record,
                            $data['password'],
                            Filament::getTenant()->id,
                            auth()->id()
                        );

                        Notification::make()
                            ->title('Aggiornamento in elaborazione')
                            ->body("L'aggiornamento dello stato per la fattura {$record->getNewInvoiceNumber()} è stato messo in coda. Riceverai una notifica al termine.")
                            ->info()
                            ->send();
                    })
                    ->disabled(function (Invoice $record) {
                        return $record->sdi_status->lockUpdate();
                    })
                    ->form([
                        TextInput::make('password')
                            ->label('Password SOAP')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->requiresConfirmation(),
                    
                // Actions\Action::make('_postal_attachments_')
                //     ->label('*Allegati spese postali*')
                //     ->color('info')
                //     ->icon('heroicon-o-paper-clip')
                //     ->visible(fn ($record) => (bool) count($record->postalExpenses) > 0)
                //     ->action(function ($record) {
                //         $disk = config('filesystems.default');
                //         $zip = new ZipArchive();
                //         $zipFileName = 'spese_postali_' . $record->id . '_' . time() . '.zip';
                //         $zipPath = storage_path('app/temp/' . $zipFileName);

                //         // Crea la directory temp se non esiste
                //         if (!Storage::disk($disk)->exists('temp')) {
                //             Storage::disk($disk)->makeDirectory('temp');
                //         }

                //         if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                //             $addedFiles = 0;

                //             foreach ($record->postalExpenses as $index => $expense) {
                //                 if ($expense->act_attachment_path && Storage::disk($disk)->exists($expense->act_attachment_path)) {
                //                     // Ottieni il contenuto del file
                //                     $fileContent = Storage::disk($disk)->get($expense->act_attachment_path);
                //                     $fileName = basename($expense->act_attachment_path);

                //                     // Aggiungi il file allo ZIP con un numero progressivo per evitare duplicati
                //                     $zip->addFromString(($index + 1) . '_' . $fileName, $fileContent);
                //                     $addedFiles++;
                //                 }
                //                 if ($expense->notify_attachment_path && Storage::disk($disk)->exists($expense->notify_attachment_path)) {
                //                     // Ottieni il contenuto del file
                //                     $fileContent = Storage::disk($disk)->get($expense->notify_attachment_path);
                //                     $fileName = basename($expense->notify_attachment_path);

                //                     // Aggiungi il file allo ZIP con un numero progressivo per evitare duplicati
                //                     $zip->addFromString(($index + 1) . '_' . $fileName, $fileContent);
                //                     $addedFiles++;
                //                 }
                //                 if ($expense->reinvoice_attachment_path && Storage::disk($disk)->exists($expense->reinvoice_attachment_path)) {
                //                     // Ottieni il contenuto del file
                //                     $fileContent = Storage::disk($disk)->get($expense->reinvoice_attachment_path);
                //                     $fileName = basename($expense->reinvoice_attachment_path);

                //                     // Aggiungi il file allo ZIP con un numero progressivo per evitare duplicati
                //                     $zip->addFromString(($index + 1) . '_' . $fileName, $fileContent);
                //                     $addedFiles++;
                //                 }
                //             }

                //             $zip->close();

                //             if ($addedFiles > 0) {
                //                 // Scarica lo ZIP e cancellalo dopo il download
                //                 return response()->download($zipPath)->deleteFileAfterSend(true);
                //             } else {
                //                 // Nessun file aggiunto, cancella lo ZIP vuoto
                //                 if (file_exists($zipPath)) {
                //                     unlink($zipPath);
                //                 }

                //                 Notification::make()
                //                     ->title('Nessun file trovato')
                //                     ->warning()
                //                     ->send();
                //             }
                //         } else {
                //             Notification::make()
                //                 ->title('Errore nella creazione dello ZIP')
                //                 ->danger()
                //                 ->send();
                //         }
                //     }),

                Actions\Action::make('postal_attachments')
                    ->label('Allegati spese postali')
                    ->color('info')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn ($record) => (bool) count($record->postalExpenses) > 0)
                    ->action(function ($record) {
                        set_time_limit(300); // 5 minuti per operazioni S3

                        $disk = config('filesystems.default');
                        $zip = new ZipArchive();
                        $zipFileName = 'spese_postali_' . $record->id . '_' . time() . '.zip';
                        $zipPath = storage_path('app/temp/' . $zipFileName);

                        // Crea directory temp locale
                        if (!is_dir(storage_path('app/temp'))) {
                            mkdir(storage_path('app/temp'), 0755, true);
                        }

                        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                            $addedFiles = 0;
                            $fileCounter = 0;

                            foreach ($record->postalExpenses as $index => $expense) {
                                $attachments = [
                                    'atto' => $expense->act_attachment_path,
                                    'notifica' => $expense->notify_attachment_path,
                                    'rifatturazione' => $expense->reinvoice_attachment_path,
                                ];

                                foreach ($attachments as $type => $path) {
                                    if (!$path || !Storage::disk($disk)->exists($path)) {
                                        continue;
                                    }

                                    try {
                                        // Per file grandi, usa stream
                                        $stream = Storage::disk($disk)->readStream($path);

                                        if ($stream === false) {
                                            throw new \Exception("Impossibile aprire lo stream per {$path}");
                                        }

                                        $tempFile = tempnam(sys_get_temp_dir(), 'zip_');
                                        file_put_contents($tempFile, $stream);

                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }

                                        $fileName = basename($path);
                                        $zipEntryName = sprintf('%02d_%s_%s', ++$fileCounter, $type, $fileName);

                                        $zip->addFile($tempFile, $zipEntryName);
                                        $addedFiles++;

                                        // Non cancellare subito, lo ZIP deve poter leggere il file
                                        register_shutdown_function(function() use ($tempFile) {
                                            if (file_exists($tempFile)) {
                                                @unlink($tempFile);
                                            }
                                        });

                                    } catch (\Exception $e) {
                                        logger()->error("Errore download file S3", [
                                            'path' => $path,
                                            'error' => $e->getMessage()
                                        ]);
                                    }
                                }
                            }

                            $zip->close();

                            if ($addedFiles > 0) {
                                return response()->download($zipPath)->deleteFileAfterSend(true);
                            } else {
                                @unlink($zipPath);

                                Notification::make()
                                    ->title('Nessun file trovato')
                                    ->warning()
                                    ->send();
                            }
                        } else {
                            Notification::make()
                                ->title('Errore nella creazione dello ZIP')
                                ->danger()
                                ->send();
                        }
                    }),



                    // Actions\Action::make('testBase64Notify')
                    //     ->label('Test Base64 + Apri Allegato')
                    //     ->icon('tabler-file-check')
                    //     ->color('gray')
                    //     ->action(function (Invoice $record) {

                    //         $expense = $record->postalExpenses->first();
                    //         $path = $expense?->notify_attachment_path;

                    //         if (empty($path)) {
                    //             Notification::make()
                    //                 ->title('Nessun allegato')
                    //                 ->body('notify_attachment_path non presente')
                    //                 ->warning()
                    //                 ->send();
                    //             return;
                    //         }

                    //         $disk = config('filesystems.default');

                    //         if (!Storage::disk($disk)->exists($path)) {
                    //             Notification::make()
                    //                 ->title('File non trovato')
                    //                 ->body("Percorso: {$path}")
                    //                 ->danger()
                    //                 ->send();
                    //             return;
                    //         }

                    //         try {
                    //             $content = Storage::disk($disk)->get($path);
                    //             $size = strlen($content);
                    //             $base64 = base64_encode($content);
                    //             $isPdf = str_starts_with($content, '%PDF-');

                    //             // Test info
                    //             $html = "
                    //                 <p><strong>Percorso:</strong> {$path}</p>
                    //                 <p><strong>Stato:</strong> " . ($isPdf ? '✅ PDF valido' : '⚠️ Non sembra un PDF') . "</p>
                    //                 <p><strong>Dimensione:</strong> " . number_format($size) . " bytes</p>
                    //                 <p><strong>Base64 Length:</strong> " . number_format(strlen($base64)) . " caratteri</p>
                    //                 <hr>
                    //                 <small>Prime 80 caratteri Base64:</small><br>
                    //                 <code style='font-size:0.8em; word-break:break-all;'>" . substr($base64, 0, 80) . "...</code>
                    //             ";

                    //             Notification::make()
                    //                 ->title('Test Base64 Allegato')
                    //                 ->body(new HtmlString($html))
                    //                 ->success()
                    //                 ->persistent()
                    //                 ->send();

                    //             // Download automatico del file
                    //             return response()->streamDownload(function () use ($content) {
                    //                 echo $content;
                    //             }, basename($path), [
                    //                 'Content-Type' => 'application/pdf',
                    //             ]);

                    //         } catch (\Exception $e) {
                    //             Notification::make()
                    //                 ->title('Errore')
                    //                 ->body($e->getMessage())
                    //                 ->danger()
                    //                 ->send();
                    //         }
                    //     })





            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
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

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->visible(fn (Invoice $record) => $record->sdi_status == SdiStatus::DA_INVIARE)
                ->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            $this->getDeleteFormAction()
                // ->visible(fn (Invoice $record) => $record->sdi_status == SdiStatus::DA_INVIARE)
                ->visible(function (Invoice $record) {
                    $toSend = $record->sdi_status == SdiStatus::DA_INVIARE;
                    if ($record->art73) {
                        $maxNumber = Invoice::where('invoice_date', $record->invoice_date)
                            ->where('art_73', true)
                            ->where('company_id', Filament::getTenant()->id)
                            ->max('number');
                        $last = $maxNumber == $record->number;
                    }
                    else if ($record->year && $record->sectional_id) {
                        $maxNumber = Invoice::where('year', $record->year)
                            ->where('sectional_id', $record->sectional_id)
                            ->where('company_id', Filament::getTenant()->id)
                            ->max('number');
                        $last = $maxNumber == $record->number;
                    }
                    return $toSend && $last;
                })
                ->extraAttributes([
                    'class' => ' md:ml-auto md:w-auto ',
                ]),
        ];
    }

    protected function getDeleteFormAction()
    {
        return Actions\DeleteAction::make('delete')
                ->requiresConfirmation()
                ->modalHeading('Conferma eliminazione documento')
                ->modalDescription('Sei sicuro di voler eliminare questo documento? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/new-invoices?')) {
                    return $this->previousUrl;
                }
                return NewInvoiceResource::getUrl('index');
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

    public static function generateDescriptionFromModel(Invoice $invoice): string
    {
        $description = '';
        $year = substr($invoice->budget_year, 2);

        if (filled($invoice->doc_type_id)) {
            // Carichiamo il tipo documento con la relazione se non è già presente
            $docType = $invoice->docType ?: DocType::with('docGroup')->find($invoice->doc_type_id);

            if ($docType?->docGroup?->name === 'Note di variazione') {

                $description = '(ab' . $year . ') ' . $docType->description;

                $reversalGroupType = ReversalGroupType::tryFrom($invoice->reversal_group_type)?->getLabel();
                if ($reversalGroupType) {
                    $description .= ' a storno ' . lcfirst($reversalGroupType);
                }

                $parent = $invoice->parent ?: Invoice::find($invoice->parent_id);
                if ($parent) {
                    $description .= ' su ' . lcfirst($parent->docType->description);
                    $description .= ' n.ro ' . $parent->getNewInvoiceNumber();
                    $description .= ' del ' . \Carbon\Carbon::parse($parent->invoice_date)->format('d/m/Y');

                    $motivation = ReversalMotivationType::find($invoice->reversal_motivation_type_id)?->name;
                    if ($motivation) {
                        $description .= ' per ' . lcfirst($motivation) . '.';
                    }
                }
            } else {
                // Caso fatture normali
                $contractDetail = $invoice->contract?->lastDetail;
                $contractDescription = $contractDetail?->invoice_description;

                $description = '(ab' . $year . ') ' . $contractDescription . ' ';

                if ($invoice->invoice_reference) {
                    if ($invoice->reference_date_from) {
                        $description .= 'per il periodo dal ' . \Carbon\Carbon::parse($invoice->reference_date_from)->format('d/m/Y');
                        if ($invoice->reference_date_to) {
                            $description .= ' al ' . \Carbon\Carbon::parse($invoice->reference_date_to)->format('d/m/Y');
                        }
                    }

                    if ($invoice->reference_number_from) {
                        $description .= ' dal verbale numero ' . $invoice->reference_number_from;
                        if ($invoice->reference_number_to) {
                            $description .= ' al verbale numero ' . $invoice->reference_number_to;
                            $total = $invoice->reference_number_to - $invoice->reference_number_from + 1;
                            if ($total > 0) {
                                $description .= ' per un totale di ' . $total . ' verbali';
                            }
                        }
                    }
                }
            }
        }

        return trim($description);
    }
}
