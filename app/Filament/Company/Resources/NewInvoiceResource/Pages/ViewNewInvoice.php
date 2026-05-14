<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\PaymentStatus;
use App\Enums\ReversalGroupType;
use App\Enums\SdiStatus;
use App\Filament\Company\Resources\InvoiceResource;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\DocType;
use App\Models\Invoice;
use App\Models\ReversalMotivationType;
use App\Models\SdiRequest;
use App\Services\AndxorSoapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                Actions\Action::make('getStatus')
                    ->label('Aggiorna stato SDI')
                    ->icon('tabler-refresh')
                    ->visible(fn($record) => $record->sdi_status->updateStatus() && $record->docType->name != 'TD00')
                    ->action(function (Invoice $record, array $data) {
                        $soapService = app(AndxorSoapService::class);
                        try {
                            $response = $soapService->updateStatus($record, $data['password']);

                            SdiRequest::create([
                                'company_id' => \Filament\Facades\Filament::getTenant()->id,
                                'request_date' => today()->format('Y-m-d'),
                                'sdi_request_type' => 'single',
                                'invoice_id' => $record->id
                            ]);

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
                    ->disabled(function (Invoice $record) {
                        // return $record->service_code != null;
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
                    ->hidden(fn(Invoice $record) => $record->parent_id || $record->sdi_status == SdiStatus::DA_INVIARE)
                    ->label('Duplica')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Duplica Fattura')
                    ->modalDescription('Vuoi creare una copia di questa fattura? La nuova fattura avrà un nuovo numero e una nuova data.')
                    ->modalSubmitActionLabel('Duplica')
                    ->form([
                        Checkbox::make('duplicate_reference')
                            ->label('Duplica i riferimenti della fattura')
                            ->live()
                            ->default(false),
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
                            $duplicateAmounts = $data['duplicate_items'] ? $data['duplicate_amounts'] : false;
                            DB::beginTransaction();

                            $newInvoice = $record->replicate();                                             // creo una nuova istanza della fattura
Log::info('Fattura replicata -------------------------------------------');
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
                            if(!$data['duplicate_reference']){
                                $newInvoice->invoice_reference = null;                                      // resetto i campi del riferimento (unici per fattura)
                                $newInvoice->reference_date_from = null;
                                $newInvoice->reference_date_to = null;
                                $newInvoice->reference_number_from = null;
                                $newInvoice->reference_number_to = null;
                                $newInvoice->total_number = null;
                            }
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
                            if(!$duplicateAmounts){
                                $newInvoice->total = 0.0;                                                   // se non devo copiare gli importi
                                $newInvoice->no_vat_total = 0.0;                                            // azzero i totali
                                $newInvoice->vat = 0.0;                                                     // azzero l'IVA
Log::info('Reset totali');
                            }
                            $newInvoice->save();                                                            // salvo la nuova fattura
                                                                                                            // (il boot method genererà automaticamente invoice_uid)
Log::info('Salvataggio');
                            if ($data['duplicate_items']) {
                                $items = $record->invoiceItems()->whereNotNull('invoice_element_id')->get();
                                foreach ($items as $key => $item) {                                         // duplico gli InvoiceItem collegati
Log::info("Id voce copiata: {$item->id}");
                                    $newItem = $item->replicate();
                                    $newItem->invoice_id = $newInvoice->id;

                                    $newItem->start_date = $item->start_date ?? null;
                                    $newItem->end_date = $item->start_date ?? null;
                                    $newItem->code = $item->code ?? null;
                                    $newItem->quantity = $data['duplicate_amounts'] ? $item->quantity : null;
                                    $newItem->measure_unit = $data['duplicate_amounts'] ? $item->measure_unit : null;
                                    $newItem->unit_price = $data['duplicate_amounts'] ? $item->unit_price : null;
                                    $newItem->amount = $data['duplicate_amounts'] ? $item->amount : 0.00;
                                    $newItem->taxable = $data['duplicate_amounts'] ? $item->taxable : 0.00;
                                    $newItem->total = $data['duplicate_amounts'] ? $item->total : 0.00;

                                    $newItem->save();

                                    $newItem->calculateTotal();
                                    $newItem->save();
                                    $newItem->checkStampDuty();                                         // operazioni per inserimenti bollo e riepiloghi
                                }
Log::info('Copia voci');
                            }

                            DB::commit();
                            $newInvoice->refresh();
                            $anyItem = $newInvoice->invoiceItems()->whereNotNull('invoice_element_id')->first();
                            if ($anyItem) {
                                $anyItem->autoInsert();
                            }
                            $newInvoice->updateTotal();
Log::info('Commit');
                            Notification::make()
                                ->title('Fattura duplicata con successo')
                                ->body('Nuova fattura creata con numero: ' . $newInvoice->getNewInvoiceNumber())
                                ->success()
                                ->send();
Log::info('Notifica');
                            // Reindirizza alla nuova fattura
                            return redirect($this->getResource()::getUrl('view', ['record' => $newInvoice]));

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore nella duplicazione')
                                ->body('Si è verificato un errore: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('nota_credito')
                    ->authorize('create')
                    ->hidden(fn(Invoice $record) => $record->parent_id || $record->sdi_status == SdiStatus::DA_INVIARE || $record->sdi_status->updateStatus())
                    ->label('Crea Nota di credito')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Crea nota di credito')
                    ->modalDescription('Vuoi creare una nota di credito per questa fattura?')
                    ->modalSubmitActionLabel('Crea')
                    ->form([
                        Select::make('reversal_group_type')
                            ->label('Tipo annullamento')
                            ->required()
                            ->live()
                            ->options(
                                collect(ReversalGroupType::cases())
                                    ->filter(fn (ReversalGroupType $enum) => $enum !== ReversalGroupType::BOTH)
                                    ->mapWithKeys(fn (ReversalGroupType $enum) => [$enum->value => $enum->getLabel()])
                            )
                            ->preload(),
                        Select::make('reversal_motivation_type_id')
                            ->label('Motivazione emissione nota di credito')
                            ->required()
                            ->live()
                            ->options(function (Get $get) {
                                $state = $get('reversal_group_type');

                                if ($state) {
                                    // Trasforma la stringa nel caso dell'Enum corrispondente
                                    $reversalGroupType = ReversalGroupType::tryFrom($state);

                                    // Verifica che la trasformazione sia riuscita e che non sia 'both'
                                    // (visto che getInverse non gestisce 'both' e andrebbe in errore)
                                    if ($reversalGroupType && $reversalGroupType !== ReversalGroupType::BOTH) {

                                        $options = ReversalMotivationType::where('reversal_group_type', '!=', $reversalGroupType->getInverse())
                                                    ->orderBy('order')
                                                    ->get();

                                        return $options->pluck('name', 'id')->toArray();
                                    }
                                }

                                return [];
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (Get $get, Set $set, Invoice $record, array $data) {
                        try {
Log::info('===== INIZIO AZIONE NOTA CREDITO =====');
                            DB::beginTransaction();

                            $newInvoice = $record->replicate();                                             // creo una nuova istanza della fattura
Log::info('Nota di credito creata ---------------------------------------');
                            // $newInvoice->contract_detail_id = $newInvoice->contract?->lastDetail?->id;      // metto l'id del dettaglio contratto in vigore
                            $newInvoice->contract_detail_id = null;                                         // non metto l'id del dettaglio contratto
Log::info('ID dettaglio contratto');
                            $newInvoice->doc_type_id = DocType::where('name', 'TD04')->first()->id;         // assegno il tipo di documento nota di credito
Log::info('Assegno il tipo di documento \'Nota di credito\'');
                            $newInvoice->reversal_group_type = $data['reversal_group_type'];                // assegno tipo annullamento
Log::info('Assegno tipo annullamento');
                            $newInvoice->reversal_motivation_type_id = $data['reversal_motivation_type_id'];// assegno motivazione emissione nota di credito
Log::info('Assegno motivazione emissione nota di credito');
                            $newInvoice->parent_id = $record->id;                                           // assegno la fattura stornata
Log::info('Assegno parent_id');
                            $newInvoice->year = now()->year;                                                // imposto anno corrente
                            $newInvoice->number = $newInvoice->calculateNextInvoiceNumber();                // genero il numero fattura

                            $newInvoice->invoice_date = now()->format('Y-m-d');                             // imposto la data di oggi
Log::info('Aggiornati numero, anno e data fattura');
                            // imposto anni di gestione e di bilancio corrente
                            // mantengo i riferimenti
                            $newInvoice->description = static::generateDescriptionFromModel($newInvoice);   // creo la nuova descrizione
                            $newInvoice->free_description = null;
Log::info('Reset descrizione');
                            $newInvoice->payment_status = PaymentStatus::WAITING;                           // imposto lo stato pagamento a 'In attesa'
                            $newInvoice->last_payment_date = null;                                          // resetto la data dell'ultimo pagamento
Log::info('Reset dati pagamento');
                            $newInvoice->total = 0.0;                                                       // azzero i totali
                            $newInvoice->no_vat_total = 0.0;                                                //
                            $newInvoice->vat = 0.0;                                                         // azzero l'IVA
Log::info('Reset totali');
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
                            //mantengo i totali
                            $newInvoice->save();                                                            // salvo la nuova fattura
                                                                                                            // (il boot method genererà automaticamente invoice_uid)
                                                                                                            // (l'hook created genererà le voci)
Log::info('Salvataggio');
                            DB::commit();
Log::info('Commit');
                            Notification::make()
                                ->title('Nota di credito con successo')
                                ->body('Nota di credito creata con numero: ' . $newInvoice->getNewInvoiceNumber())
                                ->success()
                                ->send();
Log::info('Notifica');
Log::info('===== FINE AZIONE NOTA CREDITO =====');
                            // Reindirizza alla nuova fattura
                            return redirect($this->getResource()::getUrl('view', ['record' => $newInvoice]));

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore nella creazione della nota di credito')
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

    public static function generateDescriptionFromModel(Invoice $invoice): string
    {
        $description = '';
        $year = substr($invoice->budget_year, 2);

        if (filled($invoice->doc_type_id)) {
            // Carichiamo il tipo documento con la relazione se non è già presente
            $docType = $invoice->docType ?: DocType::with('docGroup')->find($invoice->doc_type_id);

            if ($docType?->docGroup?->name === 'Note di variazione') {

                $description = '(ab' . $year . ') ' . $docType->description;

                // $reversalGroupType = ReversalGroupType::tryFrom($invoice->reversal_group_type)?->getLabel();
                $reversalGroupType = $invoice->reversal_group_type?->getLabel();
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
