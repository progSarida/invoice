<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use App\Enums\ClientType;
use App\Enums\ContractType;
use App\Jobs\CheckInvoicingContractsJob;
use Carbon\Carbon;
use Filament\Actions;
use App\Models\Invoice;
use App\Models\NewContract;
use App\Enums\InvoicingCicle;
use App\Enums\TaxType;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\ExportAction;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Exports\NewInvoiceExporter;
use App\Filament\Exports\NewInvoiceExporterSimple;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Jobs\CustomExportCsv;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\DocType;
use App\Models\ManageType;
use App\Services\AndxorSoapService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ListNewInvoices extends ListRecords
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getDefaultTableFilters(): array
    {
        return [
            'invoice_year_from' => [
                'value' => now()->year,
            ],
        ];
    }

    public function mount(): void
    {
        parent::mount();

        // if (!session()->has('checked_invoicing_' . Auth::id())) {
            // Recupero i dati
            $activeContracts = $this->getActiveContractsData();                                                                     // recupero contratti attivi con dati ultima fattura

            $invoicingContracts = $this->getInvoicingContracts($activeContracts);                                                   // recupero i contratti da fatturare

            // Eseguo il ciclo una sola volta all'accesso alla pagina
            foreach ($invoicingContracts['to_invoice'] as $contract) {
                Log::info("Contratto da fatturare: {$contract->id}");

                Notification::make('to_invoice_' . $contract->id)
                    ->title('Il contratto con ' . $contract->client->denomination . ' (' . implode('-', $contract->tax_types) . ' - ' . $contract->cig_code . ') ' . 'deve essere fatturato')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->warning()
                    // ->sendToDatabase(Auth::user());
                    ->persistent()
                    ->send();
            }

            foreach($invoicingContracts['partial'] as $partial) {
                Notification::make('to_partial_' . $contract->id)
                    ->title('Il contratto con ' . $partial->client->denomination . ' (' . implode('-', $partial->tax_types) . ' - ' . $partial->cig_code . ') ' . 'ha una fattura parzialmente stornata')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->warning()
                    // ->sendToDatabase(auth::user());
                    ->persistent()
                    ->send();
            }

            // session(['checked_invoicing_' . Auth::id() => true]);
        // }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                // ->keyBindings(['alt+n'])
                ->hidden(function () {
                    $refusedHide = $this->refusedHide();                                                                        // controllo fatture rifiutate
                    $discardedHide = $this->discardedHide();                                                                    // controllo fatture scartate
                    $lateHide = $this->lateHide();                                                                              // controllo fatture non inviate
                    $silentHide = $this->silentHide();                                                                          // controllo fatture senza esito

                    return ($refusedHide || $discardedHide || $lateHide || $silentHide);
                }),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco fatture')
                // ->iconButton() // mostro solo icona
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    $fileName = 'Fatture_' . Carbon::today()->format('d-m-Y') . '.pdf';

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = Pdf::loadHTML(
                                Blade::render('pdf.new_invoices', [
                                    'invoices' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                            ->setPaper('A4', 'landscape')
                            ->setOptions([
                                'isHtml5ParserEnabled' => true, // Abilita parser HTML5 per CSS avanzato
                                'isPhpEnabled' => true, // Abilita PHP nel template
                                'isFontSubsettingEnabled' => true, // Ottimizza i font
                            ]);

                            echo $pdf->stream();
                        }, $fileName);
                })
                ->keyBindings(['alt+s']),

            // ExportAction::make('esporta_f')
            //     ->icon('phosphor-export')
            //     ->label('Esporta (Completa)')
            //     ->color(Color::rgb('rgb(0, 153, 0)'))
            //     ->exporter(NewInvoiceExporter::class)
            //     ->modalWidth(MaxWidth::FitContent)
            //     ->keyBindings(['alt+e'])
            //     // Posizionamento checkbox campi da esportare su quattro colonne
            //     ->form(fn (ExportAction $action): array => [
            //         \Filament\Forms\Components\Fieldset::make(__('filament-actions::export.modal.form.columns.label'))
            //             ->columns(4)  // <-- qui il cambiamento
            //             ->inlineLabel()
            //             ->schema(function () use ($action): array {
            //                 return array_map(
            //                     fn (\Filament\Actions\Exports\ExportColumn $column): \Filament\Forms\Components\Split => \Filament\Forms\Components\Split::make([
            //                         \Filament\Forms\Components\Checkbox::make('isEnabled')
            //                             ->label(__('filament-actions::export.modal.form.columns.form.is_enabled.label', ['column' => $column->getName()]))
            //                             ->hiddenLabel()
            //                             ->default($column->isEnabledByDefault())
            //                             ->live()
            //                             ->grow(false),
            //                         \Filament\Forms\Components\TextInput::make('label')
            //                             ->label(__('filament-actions::export.modal.form.columns.form.label.label', ['column' => $column->getName()]))
            //                             ->hiddenLabel()
            //                             ->default($column->getLabel())
            //                             ->placeholder($column->getLabel())
            //                             ->disabled(fn (\Filament\Forms\Get $get): bool => ! $get('isEnabled'))
            //                             ->required(fn (\Filament\Forms\Get $get): bool => (bool) $get('isEnabled')),
            //                     ])
            //                         ->verticallyAlignCenter()
            //                         ->statePath($column->getName()),
            //                     $action->getExporter()::getColumns(),
            //                 );
            //             })
            //             ->statePath('columnMap'),
            //         ...$action->getExporter()::getOptionsFormComponents(),
            //     ]),

            ExportAction::make('esporta_f')
                ->icon('phosphor-export')
                ->label('Esporta (Completa)')
                ->color(Color::rgb('rgb(0, 153, 0)'))
                ->exporter(NewInvoiceExporter::class)
                ->options(function ($livewire) {
                    $maxItems = 0;
                    // $list = $livewire->getFilteredTableQuery()->withCount('invoiceItems')->get();
                    $list = $livewire->getFilteredTableQuery()
                                ->withCount(['invoiceItems' => function ($query) {
                                    $query->where('auto', false);
                                }])
                                ->get();
// dd($list);
                    foreach($list as $el){
                        if($maxItems < $el->invoice_items_count) $maxItems = $el->invoice_items_count;
                    }
// dd($maxItems);
                    return [ 'max_items' => (int) $maxItems, ];
                })
                ->modalWidth(MaxWidth::FitContent)
                ->keyBindings(['alt+e'])
                ->form(fn (ExportAction $action): array => [
                    \Filament\Forms\Components\Fieldset::make(__('filament-actions::export.modal.form.columns.label'))
                        ->columns(4)
                        ->inlineLabel()
                        ->schema(function () use ($action): array {
                            $maxItems = $action->getOptions()['max_items'] ?? 0;
                            $columns = NewInvoiceExporter::getColumns($maxItems);

                            return array_map(
                                fn (\Filament\Actions\Exports\ExportColumn $column): \Filament\Forms\Components\Split => \Filament\Forms\Components\Split::make([
                                    \Filament\Forms\Components\Checkbox::make('isEnabled')
                                        ->label(__('filament-actions::export.modal.form.columns.form.is_enabled.label', ['column' => $column->getName()]))
                                        ->hiddenLabel()
                                        ->default($column->isEnabledByDefault())
                                        ->live()
                                        ->grow(false),
                                    \Filament\Forms\Components\TextInput::make('label')
                                        ->label(__('filament-actions::export.modal.form.columns.form.label.label', ['column' => $column->getName()]))
                                        ->hiddenLabel()
                                        ->default($column->getLabel())
                                        ->placeholder($column->getLabel())
                                        ->disabled(fn (\Filament\Forms\Get $get): bool => ! $get('isEnabled'))
                                        ->required(fn (\Filament\Forms\Get $get): bool => (bool) $get('isEnabled')),
                                ])
                                    ->verticallyAlignCenter()
                                    ->statePath($column->getName()),
                                $columns
                            );
                        })
                        ->statePath('columnMap'),
                    ...$action->getExporter()::getOptionsFormComponents(),
                ]),

            ExportAction::make('esporta_s')
                ->icon('phosphor-export')
                ->label('Esporta (Semplice)')
                ->color(Color::rgb('rgb(0, 153, 0)'))
                ->exporter(NewInvoiceExporterSimple::class)
                ->modalWidth(MaxWidth::FiveExtraLarge)
                ->keyBindings(['alt+shift+e'])
                // Posizionamento checkbox campi da esportare su due colonne
                ->form(fn (ExportAction $action): array => [
                    \Filament\Forms\Components\Fieldset::make(__('filament-actions::export.modal.form.columns.label'))
                        ->columns(2)  // <-- qui il cambiamento
                        ->inlineLabel()
                        ->schema(function () use ($action): array {
                            return array_map(
                                fn (\Filament\Actions\Exports\ExportColumn $column): \Filament\Forms\Components\Split => \Filament\Forms\Components\Split::make([
                                    \Filament\Forms\Components\Checkbox::make('isEnabled')
                                        ->label(__('filament-actions::export.modal.form.columns.form.is_enabled.label', ['column' => $column->getName()]))
                                        ->hiddenLabel()
                                        ->default($column->isEnabledByDefault())
                                        ->live()
                                        ->grow(false),
                                    \Filament\Forms\Components\TextInput::make('label')
                                        ->label(__('filament-actions::export.modal.form.columns.form.label.label', ['column' => $column->getName()]))
                                        ->hiddenLabel()
                                        ->default($column->getLabel())
                                        ->placeholder($column->getLabel())
                                        ->disabled(fn (\Filament\Forms\Get $get): bool => ! $get('isEnabled'))
                                        ->required(fn (\Filament\Forms\Get $get): bool => (bool) $get('isEnabled')),
                                ])
                                    ->verticallyAlignCenter()
                                    ->statePath($column->getName()),
                                $action->getExporter()::getColumns(),
                            );
                        })
                        ->statePath('columnMap'),
                    ...$action->getExporter()::getOptionsFormComponents(),
                ]),

            Actions\Action::make('compare')
                ->icon('fluentui-column-double-compare-20-o')
                ->label('Comparata')
                ->tooltip('Stampa fatturazione comparata')
                // ->color('primary')
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->modalWidth('6xl')
                ->modalHeading('Fattura comparata')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            TextInput::make('accrual_year_1')
                                ->label('Anno competenza 1')
                                ->columnSpan(3)
                                ->required()
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1)
                                ->default(date('Y') - 1)
                                ->rules(['different:accrual_year_1']),
                            TextInput::make('accrual_year_2')
                                ->label('Anno competenza 2')
                                ->columnSpan(3)
                                ->required()
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1)
                                ->default(date('Y')),
                            Select::make('doc_type_id')
                                ->label('Tipo documento')
                                ->columnSpan(6)
                                ->options(function () {
                                    $docs = Filament::getTenant()
                                        ->docTypes()
                                        ->select('doc_types.id', 'doc_types.description')
                                        ->get();
                                    return $docs->pluck('description', 'id')->toArray();
                                })
                                ->searchable()
                                ->preload(),
                            Select::make('tax_type')
                                ->label('Entrata')
                                ->columnSpan(3)
                                ->options(TaxType::class)
                                ->searchable()
                                ->preload(),
                            Select::make('client_id')
                                ->label('Cliente')
                                ->columnSpan(5)
                                ->getSearchResultsUsing(function (string $search) {
                                    // Rimuovi spazi multipli e trim
                                    $search = trim(preg_replace('/\s+/', ' ', $search));

                                    $query = Client::query();

                                    // Cerca separatori (spazio, virgola, slash, trattino)
                                    $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                                    if (count($parts) >= 2) {
                                        // Cerca ogni "parte" all'interno del campo denomination
                                        $query->where(function ($q) use ($parts) {
                                            foreach ($parts as $part) {
                                                $q->where('denomination', 'LIKE', "%{$part}%");
                                            }
                                        });
                                    } elseif (count($parts) === 1) {
                                        $value = $parts[0];
                                        $query->where(function ($q) use ($value) {
                                            $q->where('denomination', 'LIKE', "%{$value}%");
                                        });
                                    }

                                    // Esegui la query e mappatura
                                    return $query
                                        ->orderBy('denomination', 'asc')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($record) {
                                            $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                            $denomination = $record->denomination ?? 'N/A';
                                            // $label = strtoupper("{$subtype}") . " - $denomination";
                                            $label = $denomination;

                                            return [$record->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function (?int $value) {
                                    if (!$value) { return null; }
                                    $record = Client::find($value);
                                    if (!$record) { return null; }
                                    // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                                    return $record->denomination;
                                })
                                ->getOptionLabelFromRecordUsing(
                                    // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                    fn (Model $record) => $record->denomination
                                )
                                // ->options(function () {
                                //     $docs = \Filament\Facades\Filament::getTenant()->clients()->select('clients.id', 'clients.denomination')->get();
                                //     return $docs->pluck('denomination', 'id')->toArray();
                                // })
                                ->live()
                                ->searchable('denomination')
                                // ->preload()
                                ->optionsLimit(5),
                            Select::make('manage_type_id')
                                ->label('Tipo di gestione')
                                ->columnSpan(4)
                                ->options(function () {
                                    return ManageType::orderBy('order')->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload(),
                            TextInput::make('from_budget_year')
                                ->label('Anno bilancio da')
                                ->columnSpan(2)
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1),
                            TextInput::make('to_budget_year')
                                ->label('Anno bilancio a')
                                ->columnSpan(2)
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1),
                            DatePicker::make('from_invoice_date')
                                ->label('Data fatturazione da')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->columnSpan(2),
                            DatePicker::make('to_invoice_date')
                                ->label('Data fatturazione a')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->columnSpan(2),
                            Select::make('contract_type')
                                ->label('Tipo contratto')
                                ->options(ContractType::class)
                                ->searchable()
                                ->preload()
                                ->columnSpan(3),
                        ]),
                ])
                ->action(function ($data) {
                    ini_set('memory_limit', '512M');
                    // Recupero i dati dalla form
                    $clientId = $data['client_id'] ?? null;
                    $taxType = $data['tax_type'] ?? null;
                    $contractType = $data['contract_type'] ?? null;
                    $docTypeId = $data['doc_type_id'] ?? null;
                    $manageTypeId = $data['manage_type_id'] ?? null;
                    $accrualYear1 = $data['accrual_year_1'] ?? null;
                    $accrualYear2 = $data['accrual_year_2'] ?? null;
                    $fromBudgetYear = $data['from_budget_year'] ?? null;
                    $toBudgetYear = $data['to_budget_year'] ?? null;
                    $fromInvoiceDate = $data['from_invoice_date'] ?? null;
                    $toInvoiceDate = $data['to_invoice_date'] ?? null;

                    // Log dei filtri
                    // \Log::info('Filtri ricevuti:', $data);

                    // Query contratti con relazioni caricate
                    $contracts = Filament::getTenant()
                        ->newContracts()
                        ->with([
                            'invoices' => function ($query) use ($docTypeId, $manageTypeId, $fromBudgetYear, $toBudgetYear, $fromInvoiceDate, $toInvoiceDate) {
                                $query->with([
                                    'client.city',
                                    'docType',
                                    'contract'
                                ])
                                    ->whereNotNull('client_id')
                                    ->when($docTypeId, fn($q) => $q->where('doc_type_id', $docTypeId))
                                    ->when($manageTypeId, fn($q) => $q->where('manage_type_id', $manageTypeId))
                                    ->when($fromBudgetYear, fn($q) => $q->where('budget_year', '>=', (int)$fromBudgetYear))
                                    ->when($toBudgetYear, fn($q) => $q->where('budget_year', '<=', (int)$toBudgetYear))
                                    ->when($fromInvoiceDate, fn($q) => $q->where('invoice_date', '>=', $fromInvoiceDate))
                                    ->when($toInvoiceDate, fn($q) => $q->where('invoice_date', '<=', $toInvoiceDate));
                            },
                            'client.city'
                        ])
                        ->when($clientId, fn($q) => $q->where('client_id', $clientId))
                        ->when($taxType, fn($q) => $q->whereJsonContains('tax_types', $taxType))
                        ->when($contractType, fn($q) => $q->whereHas('lastDetail', fn($q) => $q->where('contract_type', $contractType)))
                        ->get();

                    // Log dei contratti recuperati
                    // \Log::info('Contratti recuperati:', $contracts->toArray());

                    // Raggruppamento per comune
                    $param = [];
                    foreach ($contracts as $contract) {
                        $invoicesYear1 = $contract->invoices->where('accrual_year', '=', (int)$accrualYear1);
                        $invoicesYear2 = $contract->invoices->where('accrual_year', '=', (int)$accrualYear2);

                        // Log delle fatture per anno
                        // \Log::info("Fatture anno {$accrualYear1}:", $invoicesYear1->toArray());
                        // \Log::info("Fatture anno {$accrualYear2}:", $invoicesYear2->toArray());

                        $year1Data = $this->calculateYearData($invoicesYear1, $contract, $accrualYear1);
                        $year2Data = $this->calculateYearData($invoicesYear2, $contract, $accrualYear2);

                        foreach ($year1Data as $data1) {
                            $comune = $data1['comune'] ?: 'N/D';
                            $param[$comune][1][] = $data1;
                        }
                        foreach ($year2Data as $data2) {
                            $comune = $data2['comune'] ?: 'N/D';
                            $param[$comune][2][] = $data2;
                        }
                    }

                    // Log del risultato finale
                    // \Log::info('Param:', $param);

                    // Elimina comuni senza fatture per entrambi gli anni
                    $param = array_filter($param, function ($comuneData) {
                        return !empty($comuneData[1]) || !empty($comuneData[2]);
                    });

                    // Log del risultato filtrato
                    // \Log::info('Param filtrato:', $param);

                    return response()->streamDownload(function () use ($param, $data) {
                        echo Pdf::loadHTML(
                            Blade::render('pdf.compare', [
                                'data' => $param,
                                'filters' => $data,
                            ])
                        )
                            ->setPaper('A4', 'landscape')
                            ->stream();
                    }, 'Fatturazione_Comparata.pdf');

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                })
                ->keyBindings(['alt+c']),

                // Actions\Action::make('checkInvoicing')
                //     // ->hidden()
                //     ->label('Controllo contratti da fatturare')
                //     ->icon('tabler-file-search')
                //     ->action(function () {
                //         CheckInvoicingContractsJob::dispatch(Filament::getTenant(), Auth::user());
                //     }),

                // Actions\Action::make('getStatusList')
                //     ->label('Aggiorna stati SDI')
                //     ->icon('tabler-refresh')
                //     ->action(function (array $data) {
                //         $soapService = app(AndxorSoapService::class);
                //         $list = Invoice::where('flow', 'out')
                //             ->whereNotIn('sdi_status', ['rifiutata', 'accettata', 'decorrenza_termini', 'scartata', 'mancata_consegna'])
                //             ->where(function ($query) {
                //                 $query->whereNotNull('sdi_code')
                //                     ->orWhere('sdi_status', 'generata');
                //             })
                //             ->get();
                //         if (count($list) === 0)
                //             Notification::make()
                //                 ->title('Nessuna fattura da aggiornare')
                //                 ->warning()
                //                 ->send();
                //         try {
                //             $soapService->updateStatusList($list, $data['password']);
                //             Notification::make()
                //                 ->title('Stato fatture aggiornato con successo')
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
                //     ->form([
                //         TextInput::make('password')
                //             ->label('Password SOAP')
                //             ->password()
                //             ->revealable()
                //             ->required(),
                //     ])
                //     ->requiresConfirmation(),

                Actions\Action::make('getStatusList')
                    ->label('Aggiorna stati SDI')
                    ->icon('tabler-refresh')
                    ->action(function (array $data) {
                        // Dispatch del job in background
                        \App\Jobs\UpdateMultipleInvoicesSdiStatusJob::dispatch(
                            $data['password'],
                            Filament::getTenant()->id,
                            Auth::user()->id,
                        );

                        Notification::make()
                            ->title('Aggiornamento massivo avviato')
                            ->body('L\'aggiornamento degli stati SDI è stato messo in coda. Riceverai una notifica al termine.')
                            ->info()
                            ->send();
                    })
                    ->form([
                        TextInput::make('password')
                            ->label('Password SOAP')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                ->keyBindings(['alt+s']),

                // Actions\Action::make('emptyFolders')
                //     ->label('Svuota Cartella XML e PDF')
                //     ->icon('heroicon-o-trash')
                //     ->color('danger')
                //     ->requiresConfirmation() // Chiede conferma prima di procedere
                //     ->action(function () {
                //         $disk = config('filesystems.default'); // O 'public' a seconda della tua config
                //         $xmlDirectory = 'invoices/xml_files';
                //         $pdfDirectory = 'invoices/pdf_files';
                //         $emptyXml = false;
                //         $emptyPdf = false;

                //         // Recupera tutti i file nelle cartelle
                //         $xmlFiles = Storage::disk($disk)->allFiles($xmlDirectory);
                //         $pdfFiles = Storage::disk($disk)->allFiles($pdfDirectory);

                //         if (empty($xmlFiles)) {
                //             $emptyXml = true;
                //             Notification::make()
                //                 ->title('La cartella degli xml è già vuota')
                //                 ->warning()
                //                 ->duration(5000)
                //                 ->send();
                //         }
                //         if (empty($pdfFiles)) {
                //             $emptyPdf = true;
                //             Notification::make()
                //                 ->title('La cartella dei pdf è già vuota')
                //                 ->warning()
                //                 ->duration(5000)
                //                 ->send();
                //         }

                //         // Elimina i file
                //         if (Storage::disk($disk)->delete($xmlFiles)) {
                //             if(!$emptyXml){
                //                 Notification::make()
                //                     ->title('Cartella file xml svuotata con successo')
                //                     ->success()
                //                     ->duration(5000)
                //                     ->send();
                //             }
                //         } else {
                //             Notification::make()
                //                 ->title('Errore durante lo svuotamento della cartella dei xml ')
                //                 ->danger()
                //                 ->duration(5000)
                //                 ->send();
                //         }

                //         if (Storage::disk($disk)->delete($pdfFiles)) {
                //             if(!$emptyPdf){
                //                 Notification::make()
                //                     ->title('Cartella file pdf svuotata con successo')
                //                     ->success()
                //                     ->duration(5000)
                //                     ->send();
                //             }
                //         } else {
                //             Notification::make()
                //                 ->title('Errore durante lo svuotamento della cartella dei pdf')
                //                 ->danger()
                //                 ->duration(5000)
                //                 ->send();
                //         }
                //     })
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }

    private function refusedHide()                                                              // controllo fatture rifiutate
    {
        $refusedHide = false;
        $refused = Invoice::where('flow', 'out')->where('sdi_status', 'rifiutata');
        $refusedE = Invoice::where('flow', 'out')->where('sdi_status', 'rifiuto_emesso');

        if ($refused->count() > 0) {
            Notification::make('refused_status')
                ->title('Sono presenti fatture rifiutate<br>(Status: NE EC02 - Rifiuto)<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                ->color('danger')
                ->icon('fluentui-presence-blocked-20-o')
                ->persistent()
                ->send();
            return true;
        }

        if ($refusedE->count() > 0) {                                                           // link fatture rifiutate
            $invoicesR = $refusedE->get();
            $refused = false;
            foreach ($invoicesR as $index => $el) {
                if (!Invoice::where('parent_id', $el->id)->exists()) {
                    Notification::make('refused_credit_note_' . $el->id)
                        ->title('Emettere la nota di credito per la fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year)
                        ->color('gray')
                        ->icon('phosphor-warning-circle-light')
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('edit')
                                ->label('Vai alla fattura')
                                ->url(NewInvoiceResource::getUrl('view', ['record' => $el->id]))
                                ->color('warning'),
                        ])
                        ->send();
                    // $refused = true;
                }
            }
            if ($refused) return $refused;
        }

        return false;
    }

    private function discardedHide()                                                            // controllo fatture scartate
    {
        $discarded = Invoice::where('flow', 'out')->where('sdi_status', 'scartata');

        if ($discarded->count() > 0) {                                                          // link fatture scartate
            $invoicesD = $discarded->get();
            foreach ($invoicesD as $index => $el) {
                $daysLeft = now()->diffInDays($el->invoice_date, true);
                // if($el->id = 6348) dd($daysLeft);
                if($daysLeft <= 12){
                    Notification::make('discarded_manage_' . $el->id)
                        ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                Correggere i dati errati e reinviare<br>")
                        ->color('gray')
                        ->icon('phosphor-warning-circle-light')
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('edit')
                                ->label('Vai alla fattura')
                                ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                ->color('warning'),
                        ])
                        ->send();
                }
                else{
                    Notification::make('discarded_manage_' . $el->id)
                        ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                Modificare stato (in Scarto validato) ed emettere una nuova fattura<br>[Fattura collegata alla numero
                                " . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . " del " . \Carbon\Carbon::parse($el->invoice_date)->format('d/m/Y') . " scartata dallo SDI]")
                        ->color('gray')
                        ->icon('phosphor-warning-circle-light')
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('edit')
                                ->label('Vai alla fattura')
                                ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                ->color('warning'),
                        ])
                        ->send();
                }
            }
            return true;
        }

        return false;
    }

    private function lateHide()                                                                 // controllo fatture non inviate da due giorni
    {
        $forewarningId = DocType::where('description', 'Preavviso di fattura')->first()->id;
        $late = Invoice::where('doc_type_id', '!=', $forewarningId)
                    ->where('flow', 'out')
                    ->where('sdi_status', 'da_inviare')
                    ->where('invoice_date', '<', Carbon::now()->subDays(2));

        if ($late->count() > 0) {                                                                   // blocco per fatture con data vecchia di più di 2 giorni
            Notification::make('late_status')
                ->title('Sono presenti fatture da inviare da almeno 2 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                ->color('danger')
                ->icon('fluentui-presence-blocked-20-o')
                ->persistent()
                ->send();
            return true;
        }

        return false;
    }

    private function silentHide()                                                               // controllo fatture senza esito inviate da più di 3 giorni
    {
        $silent = Invoice::where('flow', 'out')->whereIn('sdi_status', ['inviata', 'trasmessa_sdi', 'generata'])->where('sdi_date', '<', Carbon::now()->subDays(3));

        if ($silent->count() > 0) {                                                             // blocco per fatture senza esito inviate da più di 3 giorni
            Notification::make('silent_status')
                ->title('Sono presenti fatture senza esito da oltre 3 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                ->color('danger')
                ->icon('fluentui-presence-blocked-20-o')
                ->persistent()
                ->send();
            return true;
        }

        return false;
    }

    private function getActiveContractsOld()                                                       // recupera i contratti ancora attivi
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::leftJoin('invoices', function($join) {                        // recupero i contratti attivi
                $join->on('new_contracts.id', '=', 'invoices.contract_id')
                    ->where('invoices.flow', '=', 'out');                                       // controllo solo sulle fatture nuove
            })
            ->select('new_contracts.*')
            ->selectRaw('COALESCE(SUM(invoices.total), 0) as total_invoiced')
            ->where('new_contracts.start_validity_date', '<=', $today)                          // recupero i contratti iniziati
            ->where(function ($query) use ($today) {
                $query->whereNull('new_contracts.end_validity_date')                            // recupero i contratti senza data di termine
                    ->orWhere('new_contracts.end_validity_date', '>=', $today);                 // recupero i contratti per cui non si è raggiunta la data di termine
            })
            ->groupBy('new_contracts.id')
            ->havingRaw('new_contracts.amount > total_invoiced')                                // recupero quelli per cui non si è raggiunto l'importo massimo
            ->get();

        // dd($contracts);

        return $contracts;
    }

    private function getActiveContracts()                                                       // recupera i contratti ancora attivi
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::where('start_validity_date', '<=', $today)                    // seleziono i contratti base
            ->where('company_id', Filament::getTenant()->id)
            ->where('closed', false)
            // ->where(function ($query) use ($today) {
            //     $query->whereNull('end_validity_date')
            //         ->orWhere('end_validity_date', '>=', $today);
            // })
            ->get();

        return $contracts;
    }

    private function getActiveContractsDataOld()                                                   // recupera i contratti ancora attivi con data, numero, sezionario e anno dell'ultima fattura emessa
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::where('start_validity_date', '<=', $today)                    // seleziono i contratti base
            ->where('company_id', Filament::getTenant()->id)
            ->where('closed', false)
            // ->where(function ($query) use ($today) {
            //     $query->whereNull('end_validity_date')
            //         ->orWhere('end_validity_date', '>=', $today);
            // })
            ->get();

        $activeContracts = collect();

        foreach ($contracts as $contract) {                                                     // per ogni contratto calcoliamo le informazioni aggiuntive

            $totalInvoiced = Invoice::where('contract_id', $contract->id)                       // calcolo il totale fatturato
                ->where('flow', 'out')                                                          // non necessario perchè le invoice legate ai NewContract sono tutte con flow = 'out'
                ->sum('total') ?? 0;

            if ($contract->amount > $totalInvoiced) {                                           // verifico se il contratto soddisfa la condizione

                $lastInvoice = Invoice::where('contract_id', $contract->id)                     // rovo l'ultima fattura
                    ->where('flow', 'out')
                    ->orderBy('invoice_date', 'desc')
                    ->first();
                                                                                                // aggiungo i dati calcolati al contratto
                $contract->total_invoiced = $totalInvoiced;                                     // totale fatturato
                $contract->last_invoice_date = $lastInvoice?->invoice_date;                     // data ultima fattura
                $contract->last_invoice_number = $lastInvoice?->number;                         // numero ultima fattura
                $contract->last_invoice_sectional_id = $lastInvoice?->sectional_id;             // sezionario ultima fattura
                $contract->last_invoice_year = $lastInvoice?->year;                             // anno ultima fattura

                // $notRound = BankAccount::find($lastInvoice?->bank_account_id)?->name != 'Giroconto';
                // if($contract->client?->type?->value == 'public' && $notRound)
                //     $contract->last_invoice_total = $lastInvoice?->no_vat_total;                // totale senza iva ultima fattura
                // else
                    $contract->last_invoice_total = $lastInvoice?->total;                       // totale ultima fattura

                $contract->last_invoice_notes = $lastInvoice?->total_notes;                     // totale note di credito su ultima fattura

                $activeContracts->push($contract);                                              // aggiungo alla collezione dei contratti validi
            }
        }

        // dd($activeContracts);

        return $activeContracts;
    }

    private function getActiveContractsData()                                                   // recupera i contratti ancora attivi con data, numero, sezionario e anno dell'ultima fattura emessa
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::where('start_validity_date', '<=', $today)                    // seleziono i contratti base
            ->where('company_id', Filament::getTenant()->id)
            ->where('closed', false)
            // ->where(function ($query) use ($today) {
            //     $query->whereNull('end_validity_date')
            //         ->orWhere('end_validity_date', '>=', $today);
            // })
            ->get();

        $activeContracts = collect();

        foreach ($contracts as $contract) {                                                     // per ogni contratto calcoliamo le informazioni aggiuntive

            $lastInvoice = Invoice::where('contract_id', $contract->id)                     // trovo l'ultima fattura
                ->where('flow', 'out')
                ->orderBy('invoice_date', 'desc')
                ->first();

            // $notRound = BankAccount::find($lastInvoice?->bank_account_id)?->name != 'Giroconto';
            // $query = Invoice::where('contract_id', $contract->id)                               // calcolo il totale fatturato
            //     ->where('flow', 'out');                                                         // non necessario perchè le invoice legate ai NewContract sono tutte con flow = 'out'
            // if($contract->client?->type == ClientType::PUBLIC && $notRound)
            //     $totalInvoiced = $query->sum('no_vat_total') ?? 0;                              // se contratto con PA sommo il totale senza iva
            // else
            //     $totalInvoiced = $query->sum('total') ?? 0;                                     // se contratto con privato sommo il totale con iva

            $imponibile = Invoice::where('contract_id', $contract->id)                            // calcolo il totale imponibile fatturato
                ->selectRaw('SUM(CASE WHEN parent_id IS NULL THEN no_vat_total ELSE -no_vat_total END) as total')
                ->value('total') ?? 0;

            $iva = Invoice::where('contract_id', $contract->id)                                   // calcolo il totale iva fatturato
                ->selectRaw('SUM(CASE WHEN parent_id IS NULL THEN vat ELSE -vat END) as total')
                ->value('total') ?? 0;

            $totalInvoiced = $imponibile + $iva;

            if ($contract->amount > $totalInvoiced) {                                           // verifico se il contratto soddisfa la condizione
                                                                                                // aggiungo i dati calcolati al contratto
                $contract->total_invoiced = $totalInvoiced;                                     // totale fatturato
                $contract->last_invoice_date = $lastInvoice?->invoice_date;                     // data ultima fattura
                $contract->last_invoice_number = $lastInvoice?->number;                         // numero ultima fattura
                $contract->last_invoice_sectional_id = $lastInvoice?->sectional_id;             // sezionario ultima fattura
                $contract->last_invoice_year = $lastInvoice?->year;                             // anno ultima fattura

                // if($contract->client?->type?->value == 'public' && $notRound)
                //     $contract->last_invoice_total = $lastInvoice?->no_vat_total;                // totale senza iva ultima fattura
                // else
                    $contract->last_invoice_total = $lastInvoice?->total;                       // totale ultima fattura
                $contract->last_invoice_notes = $lastInvoice?->total_notes;                     // totale note di credito su ultima fattura

                $activeContracts->push($contract);                                              // aggiungo alla collezione dei contratti validi
            }
        }

        // dd($activeContracts);

        return $activeContracts;
    }

    private function getInvoicingContracts($activeContracts)                                    // recupero i contratti da fatturare
    {
        $invoicingContracts = collect();
        $partialInvoicingContracts = collect();

        foreach($activeContracts as $contract) {
            $invoicingCycle = $contract->invoicing_cycle;
Log::info("Contratto: {$contract->id} ---------------------------------------------------------------------------------------------");
Log::info("Data ultima fattura: {$contract->last_invoice_date}");
            if ($invoicingCycle === null) { continue; }                                         // se il ciclo di fatturazione è null salto il contratto

            if ($invoicingCycle instanceof InvoicingCicle) { $cycle = $invoicingCycle; }
            else { $cycle = InvoicingCicle::from($invoicingCycle); }

            $invoiceTime = match($cycle) {                                                      // controllo se il termine di fatturazione è passato
                InvoicingCicle::ONCE => $this->checkOnceInvoicing($contract),
                InvoicingCicle::MONTHLY => $this->checkMonthlyInvoicing($contract),
                InvoicingCicle::BIMONTHLY => $this->checkBimonthlyInvoicing($contract),
                InvoicingCicle::QUARTERLY => $this->checkQuarterlyInvoicing($contract),
                InvoicingCicle::SEMIANNUALLY => $this->checkSemiannuallyInvoicing($contract),
                InvoicingCicle::ANNUALLY => $this->checkAnnuallyInvoicing($contract),
            };
$toInvoice = $invoiceTime ? 'Si' : 'No';
Log::info("Da fatturare: {$toInvoice}");
            if ($invoiceTime) {
                if($contract->last_invoice_notes > 0 && $contract->last_invoice_notes < $contract->last_invoice_total && !$this->notificationExpired($contract))
                    $partialInvoicingContracts->push($contract);                                // se notes non è zero ma è minore di total e lo storno ha meno di sei mesi => partialInvoicingContracts
                else
                    $invoicingContracts->push($contract);                                       // se notes è zero o (maggiore o uguale a total) => invoicingContract
            }
        }

        $output['to_invoice'] = $invoicingContracts;
        $output['partial'] = $partialInvoicingContracts;
// dd($output);
        return $output;
    }

    private function checkOnceInvoicing($contract): bool
    {
        $actualInvoices = Invoice::where('contract_id', $contract->id)->count();                // Controllo quante fatture sono state effettivamente emesse

        return $actualInvoices < 1;                                                             // Controllo che si debba creare la fattura attuale
    }

    private function checkMonthlyInvoicingOld($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 1;                                        // controllo che sia passato un mese dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 1;                                  // controllo che sia passato un mese dalla data dell'ultima fattura
        }
    }

    private function checkMonthlyInvoicing($contract): bool
    {
        $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 1);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 1);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkBimonthlyInvoicingOld($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 2;                                        // controllo che siano passati due mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 2;                                  // controllo che siano passati due mesi dalla data dell'ultima fattura
        }
    }

    private function checkBimonthlyInvoicing($contract): bool
    {
       $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 2);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 2);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkQuarterlyInvoicingOld($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 3;                                        // controllo siano passati tre mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 3;                                  // controllo che siano passati tre mesi dalla data dell'ultima fattura
        }
    }

    private function checkQuarterlyInvoicing($contract): bool
    {
       $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 3);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 3);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
}

    private function checkSemiannuallyInvoicingOld($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 6;                                        // controllo che siano passati sei mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 6;                                  // controllo che siano passati sei mesi dalla data dell'ultima fattura
        }
    }

    private function checkSemiannuallyInvoicing($contract): bool
    {
       $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 6);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 6);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkAnnuallyInvoicingOld($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 12;                                       // controllo che sia passato un anno dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 12;                                 // controllo che sia passato un anno dalla data dell'ultima fattura
        }
    }

    private function checkAnnuallyInvoicing($contract): bool
    {
        $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 12);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 12);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function notificationExpired($contract): bool
    {
        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        return $lastInvoiceDate->diffInMonths(now()) > 6;                                       // controllo che siano passati sei mesi dalla data dell'ultima fattura
    }

    private function calculateYearData($invoices, $contract, $accrualYear)
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        $result = [];

        // Raggruppa per codice città
        $byComune = $invoices->groupBy(function ($invoice) {
            if (!$invoice->client) {
                return 'N/D';
            }
            return $invoice->client->city && $invoice->client->city->code
                ? $invoice->client->city->code
                : ($invoice->client->comune ?? $invoice->client->denomination ?? 'N/D');
        });

        foreach ($byComune as $comune => $invoicesByComune) {
            // AGGIUNTO: Raggruppa per anno di bilancio
            $byBudgetYear = $invoicesByComune->groupBy(function ($invoice) {
                return $invoice->budget_year ?? 'N/D';
            });

            foreach ($byBudgetYear as $budgetYear => $invoicesByBudgetYear) {
                // Raggruppa per tributo
                $byTributo = $invoicesByBudgetYear->groupBy(function ($invoice) {
                    return $invoice->tax_type ? ($invoice->tax_type->value ?? '') : '';
                });

                foreach ($byTributo as $tributo => $invoicesByTributo) {
                    // Raggruppa per payment_type (dal contratto)
                    $byTipoGestione = $invoicesByTributo->groupBy(function ($invoice) {
                        return $invoice->contract ? ($invoice->contract->payment_type ?? '') : '';
                    });

                    foreach ($byTipoGestione as $tipo_gestione => $invoicesByTipoGestione) {
                        // Raggruppa per tipo_fattura
                        $byTipoFattura = $invoicesByTipoGestione->groupBy(function ($invoice) {
                            return $invoice->doc_type_id ?? '';
                        });

                        foreach ($byTipoFattura as $tipo_fattura => $invoicesGroup) {
                            $total = $invoicesGroup->sum(function ($invoice) use ($contract) {
                                return $contract->client && $contract->client?->type === ClientType::PUBLIC
                                    ? ($invoice->no_vat_total ?? 0)
                                    : ($invoice->total ?? 0);
                            });
                            $accredito = $invoicesGroup->sum('total_payment');
                            $nota_credito = $invoicesGroup->sum('total_notes');

                            $result[] = [
                                'invoices' => $invoicesGroup->toArray(),
                                'comune' => $comune,
                                'anno' => $budgetYear, // Usa l'anno di bilancio dal raggruppamento
                                'tributo' => $tributo,
                                'tipo_gestione' => $tipo_gestione,
                                'tipo_fattura' => $tipo_fattura,
                                'importo' => $total,
                                'accredito' => $accredito,
                                'nota_credito' => $nota_credito,
                            ];
                        }
                    }
                }
            }
        }

        // \Log::info('Risultato calculateYearData:', $result);
        return $result;
    }
}
