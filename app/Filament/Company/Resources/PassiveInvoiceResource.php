<?php

namespace App\Filament\Company\Resources;

use App\Enums\PiValidationStatus;
use App\Filament\Company\Resources\PassiveInvoiceResource\Pages;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassiveItemsRelationManager;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassivePaymentsRelationManager;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\VariationNotesRelationManager;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\AttachmentsSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\DescriptionSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\DocumentSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\PaymentDataSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\ReferencesSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\SdiStatusSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections\TotalsSection;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\DateFilters;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\DocumentFilters;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\PaymentFilters;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\RegistrationFilters;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\SupplierFilters;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\TotalFilters;
use App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters\ValidationFilters;
use App\Filament\Exports\PassiveInvoiceExporter;
use App\Models\PassiveInvoice;
use App\Models\PiValidation;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

class PassiveInvoiceResource extends Resource
{
    protected static ?string $model = PassiveInvoice::class;

    public static ?string $pluralModelLabel = 'Fatture passive';

    public static ?string $modelLabel = 'Fattura passiva';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            // ->disabled()
            ->schema([

                Placeholder::make('pi_validation')                                                          //
                    ->label('')                                                                             //
                    // ->visible(fn($record) => $record && filled($record->pi_validation_id))               //
                    ->visible(fn($record) => $record )                                                      //
                    ->content(function ($record) {                                                          //
                        if (!$record?->pi_validation_id) {                                                  //
                            return 'Nessuna validazione selezionata';                                       //
                        }                                                                                   //
                                                                                                            //
                        return optional($record?->piValidation)->name;                                      //
                    })                                                                                      //
                    ->extraAttributes(function ($record) {                                                  //
                        $statusEnum = $record?->piValidation?->pi_validation_status;                        //
                                                                                                            //
                        $color = $statusEnum?->getColor() ?? 'gray';                                        //
                                                                                                            //
                        $bgColorClass = "bg-{$color}-100";                                                  // Validazione
                                                                                                            //
                        $borderColorClass = "border-{$color}-400";                                          //
                                                                                                            //
                        $baseClasses = 'text-lg font-semibold border pb-1 pt-2';                            //
                                                                                                            //
                        $customClasses = [                                                                  //
                            'rounded-lg', // Arrotondamento angoli                                          //
                                                                                                            //
                            'text-center', // Testo centrato                                                //
                                                                                                            //
                            $bgColorClass, // Colore di sfondo dinamico                                     //
                            $borderColorClass,                                                              //
                            'text-gray-900', // Assicura che il testo sia leggibile su sfondi chiari        //
                        ];                                                                                  //
                                                                                                            //
                        return [                                                                            //
                            'class' => $baseClasses . ' ' . implode(' ', $customClasses),                   //
                        ];
                    })
                    ->columnSpan('full'),

                ReferencesSection::make(),                                                                  // Riferimenti (fornitore, fattura stornata)
                DocumentSection::make(),                                                                    // Dati documento

                Livewire::make(                                                                             // 
                    PassiveItemsRelationManager::class,                                                     // 
                    fn (?PassiveInvoice $record, $livewire) => [                                            // 
                        'ownerRecord' => $record,                                                           // 
                        'pageClass'   => $livewire::class,                                                  // Voci documento
                    ],                                                                                      // 
                )                                                                                           // 
                ->visible(fn (?PassiveInvoice $record) => $record !== null)                                 // 
                ->key('rm-invoice-items')                                                                   // 
                ->columnSpanFull(),                                                                         // 

                DescriptionSection::make(),                                                                 // Descrizione
                TotalsSection::make(),                                                                      // Totali
                PaymentDataSection::make(),                                                                 // Dati pagamento
                SdiStatusSection::make(),                                                                   // Stato SDI
                AttachmentsSection::make(),                                                                 // Allegati

                DatePicker::make('created_at')                                                              //
                    ->label('Data inserimento')                                                             //
                    ->extraInputAttributes(['class' => 'text-center'])                                      // Data inserimento
                    ->columnSpan(1)                                                                         //
                    ->disabled(),                                                                           //

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'piValidationUser']))
            ->defaultSort(fn (Builder $query) => $query->orderBy('invoice_date', 'desc')->orderBy('id', 'desc'))
            ->columns([
                TextColumn::make('docType.description')
                    ->label('🔍 Tipo documento')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record?->docType->description)
                    ->sortable(),
                TextColumn::make('number')
                    ->label('🔍 Numero')
                    ->alignRight()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.denomination')
                    ->label('🔍 Fornitore')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record?->supplier->denomination)
                    ->sortable(),
                TextColumn::make('description')
                    ->label('🔍 Descrizione')
                    ->searchable()
                    ->wrap()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record?->description)
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Totale')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    // L'elenco contiene anche le note come righe a sé: nel totale di colonna le note
                    // di credito vanno sottratte, mentre quelle di debito si sommano come le fatture.
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(fn (QueryBuilder $query): float => (float) (clone $query)
                                ->reorder()
                                ->selectRaw("COALESCE(SUM(CASE WHEN passive_invoices.doc_type = 'TD04'
                                                              THEN -passive_invoices.total
                                                              ELSE passive_invoices.total END), 0) as amount")
                                ->value('amount'))
                            ->money('EUR', true, 'it_IT'),
                    ]),
                TextColumn::make('payment_deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                // Tables\Columns\IconColumn::make('sdi_status')
                //     ->label('Stato')
                //     // ->tooltip(fn ($state): string => $state)
                //     ->sortable(),
                // Calcolate sui documenti collegati, non su colonne del database: non sono ordinabili,
                // ma il totale di colonna viene ricavato in query dalle note collegate alle fatture filtrate
                TextColumn::make('total_notes')
                    ->label('Note di credito')
                    ->money('EUR')
                    ->alignRight()
                    ->state(fn (PassiveInvoice $record): float => $record->getCreditNotesTotal())
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(fn (QueryBuilder $query): float => static::sumCreditNotes($query))
                            ->money('EUR', true, 'it_IT'),
                    ]),
                Tables\Columns\IconColumn::make('piValidation.pi_validation_status')
                    ->label('Validazione')
                    ->default(PiValidationStatus::NO_STATUS)
                    ->tooltip(fn ($record): string => $record?->piValidation ? $record?->piValidation->name : 'Non validata')
                    ->sortable(),
                TextColumn::make('pi_validation_date')
                    ->label('Data validazione')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('piValidationUser.name')
                    ->label('Validata da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_payment')
                    ->label('Pagato')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ]),
                TextColumn::make('residue')
                    ->label('Residuo')
                    ->money('EUR')
                    ->alignRight()
                    // Una nota di credito non ha un residuo proprio: rettifica una fattura, ed è su
                    // quella che il residuo viene calcolato.
                    ->state(fn (PassiveInvoice $record): float => $record->doc_type === 'TD04' ? 0.00 : $record->getResidue())
                    // Il totale è la somma dei valori di riga: per ogni documento total meno i
                    // pagamenti meno le note collegate, e zero per le note di credito. Ripete in
                    // SQL quello che getResidue() calcola sul singolo record.
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(fn (QueryBuilder $query): float => (float) (clone $query)
                                ->reorder()
                                ->selectRaw("COALESCE(SUM(
                                        CASE WHEN passive_invoices.doc_type = 'TD04' THEN 0
                                             ELSE COALESCE(passive_invoices.total, 0)
                                                  - COALESCE(passive_invoices.total_payment, 0)
                                                  - COALESCE((SELECT SUM(CASE WHEN n.doc_type = 'TD04' THEN n.total
                                                                              WHEN n.doc_type = 'TD05' THEN -n.total
                                                                              ELSE 0 END)
                                                              FROM passive_invoices n
                                                              WHERE n.parent_id = passive_invoices.id), 0)
                                        END), 0) as amount")
                                ->value('amount'))
                            ->money('EUR', true, 'it_IT'),
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data inserimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label('Registrata da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('updated_at')
                //     ->date('d/m/Y')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersFormWidth(MaxWidth::SevenExtraLarge)
            ->filtersFormColumns(24)
            ->filters([
                ...SupplierFilters::make(),
                ...DocumentFilters::make(),
                ...TotalFilters::make(),
                ...PaymentFilters::make(),
                ...DateFilters::make(),
                ...ValidationFilters::make(),
                ...RegistrationFilters::make(),
            ])
            ->deferFilters()                                    // i filtri si applicano solo cliccando il pulsante
            ->filtersApplyAction(
                fn (Tables\Actions\Action $action) => $action
                    ->label('Applica filtri')
                    ->icon('heroicon-m-magnifying-glass')
                    // allineo il pulsante a destra del pannello dei filtri
                    ->extraAttributes(['style' => 'display: flex; width: fit-content; margin-inline-start: auto;']),
            )
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF')
                    ->icon('phosphor-file-pdf-duotone')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record?->pdf_path ? Storage::temporaryUrl($record?->pdf_path,now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record && $record?->pdf_path),
                Action::make('download_xml')
                    ->label('')
                    ->tooltip('Scarica XML')
                    ->icon('tabler-file-type-xml')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record?->xml_path ? Storage::temporaryUrl($record?->xml_path,now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record && $record?->xml_path),
                Action::make('validate')
                    ->label('Valida fattura')
                    // ->icon('fluentui-checkmark-starburst-20-o')
                    ->requiresConfirmation()
                    ->visible(fn (PassiveInvoice $record) => !$record?->pi_validation_id && auth()->user()->can('update', $record))
                    ->form([
                        Select::make('pi_validation_id')
                            ->label('')
                            ->placeholder('Da validare')
                            ->options(
                                PiValidation::orderBy('order', 'asc')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->default(fn (PassiveInvoice $record) => $record->pi_validation_id),
                    ])
                    ->action(function (PassiveInvoice $record, array $data) {
                        $record->update([
                            'pi_validation_id' => $data['pi_validation_id'],
                        ]);
                    })
                    ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make()->visible(fn (): bool => Auth::user()->isManager()),
                    Tables\Actions\BulkAction::make('list')
                        ->label('Lista selezionate')
                        // ->icon('heroicon-m-arrow-down-tray')
                        ->icon('heroicon-o-printer')
                        ->color(Color::rgb('rgb(255, 0, 0)'))
                        ->openUrlInNewTab()
                        // ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $fileName = 'Fatture_' . Carbon::today()->format('d-m-Y') . '.pdf';
                            return response()
                                ->streamDownload(function () use ($records) {
                                    $pdf = Pdf::loadHTML(
                                        Blade::render('pdf.passive_invoices', [
                                            'invoices' => $records,
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
                        }),
                    Tables\Actions\BulkAction::make('pdfs')
                        ->label('Scarica PDF')
                        ->icon('phosphor-file-pdf-duotone')
                        ->color(Color::rgb('rgb(255, 0, 0)'))
                        // ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            // Filtra solo le fatture che hanno un PDF disponibile
                            $recordsWithPdf = $records->filter(function ($record) {
                                return !empty($record->pdf_path) &&
                                    Storage::disk(config('filesystems.default'))->exists($record->pdf_path);
                            });

                            if ($recordsWithPdf->isEmpty()) {
                                Notification::make()
                                    ->title('Nessun PDF disponibile')
                                    ->body('Nessuna delle fatture selezionate ha un PDF disponibile per il download.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Se c'è solo un PDF, scaricalo direttamente
                            if ($recordsWithPdf->count() === 1) {
                                $record = $recordsWithPdf->first();
                                return response()->download(
                                    Storage::disk(config('filesystems.default'))->path($record->pdf_path),
                                    basename($record->pdf_path)
                                );
                            }

                            // Se ci sono più PDF, crea un archivio ZIP
                            $zipFileName = 'Fatture_PDF_' . now()->format('d-m-Y_His') . '.zip';
                            $zipPath = storage_path('app/temp/' . $zipFileName);

                            // Crea la directory temp se non esiste
                            if (!file_exists(storage_path('app/temp'))) {
                                mkdir(storage_path('app/temp'), 0755, true);
                            }

                            $zip = new \ZipArchive();

                            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                                foreach ($recordsWithPdf as $record) {
                                    $pdfPath = Storage::disk(config('filesystems.default'))->path($record->pdf_path);

                                    // Mantieni il nome originale del file
                                    $fileName = basename($record->pdf_path);

                                    $zip->addFile($pdfPath, $fileName);
                                }

                                $zip->close();

                                $skippedCount = $records->count() - $recordsWithPdf->count();

                                if ($skippedCount > 0) {
                                    Notification::make()
                                        ->title('Download completato con avvisi')
                                        ->body("Scaricati {$recordsWithPdf->count()} PDF. {$skippedCount} fatture non avevano PDF disponibili.")
                                        ->warning()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Download completato')
                                        ->body("Scaricati {$recordsWithPdf->count()} PDF.")
                                        ->success()
                                        ->send();
                                }

                                return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
                            } else {
                                Notification::make()
                                    ->title('Errore')
                                    ->body('Impossibile creare l\'archivio ZIP.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('xmls')
                        ->label('Scarica XML')
                        ->icon('tabler-file-type-xml')
                        ->color(Color::rgb('rgb(255, 123, 0)'))
                        // ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            // Filtra solo le fatture che hanno un PDF disponibile
                            $recordsWithXml = $records->filter(function ($record) {
                                return !empty($record->xml_path) &&
                                    Storage::disk(config('filesystems.default'))->exists($record->xml_path);
                            });

                            if ($recordsWithXml->isEmpty()) {
                                Notification::make()
                                    ->title('Nessun XML disponibile')
                                    ->body('Nessuna delle fatture selezionate ha un XML disponibile per il download.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Se c'è solo un XML, scaricalo direttamente
                            if ($recordsWithXml->count() === 1) {
                                $record = $recordsWithXml->first();
                                return response()->download(
                                    Storage::disk(config('filesystems.default'))->path($record->xml_path),
                                    basename($record->xml_path)
                                );
                            }

                            // Se ci sono più XML, crea un archivio ZIP
                            $zipFileName = 'Fatture_PDF_' . now()->format('d-m-Y_His') . '.zip';
                            $zipPath = storage_path('app/temp/' . $zipFileName);

                            // Crea la directory temp se non esiste
                            if (!file_exists(storage_path('app/temp'))) {
                                mkdir(storage_path('app/temp'), 0755, true);
                            }

                            $zip = new \ZipArchive();

                            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                                foreach ($recordsWithXml as $record) {
                                    $pdfPath = Storage::disk(config('filesystems.default'))->path($record->xml_path);

                                    // Mantieni il nome originale del file
                                    $fileName = basename($record->xml_path);

                                    $zip->addFile($pdfPath, $fileName);
                                }

                                $zip->close();

                                $skippedCount = $records->count() - $recordsWithXml->count();

                                if ($skippedCount > 0) {
                                    Notification::make()
                                        ->title('Download completato con avvisi')
                                        ->body("Scaricati {$recordsWithXml->count()} XML. {$skippedCount} fatture non avevano PDF disponibili.")
                                        ->warning()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Download completato')
                                        ->body("Scaricati {$recordsWithXml->count()} XML.")
                                        ->success()
                                        ->send();
                                }

                                return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
                            } else {
                                Notification::make()
                                    ->title('Errore')
                                    ->body('Impossibile creare l\'archivio ZIP.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\ExportBulkAction::make('xls')
                        ->label('Esporta in Excel')
                        ->exporter(PassiveInvoiceExporter::class)
                        ->color(Color::rgb('rgb(0, 153, 0)'))
                        ->icon('phosphor-file-xls-duotone'),
                        // ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Totale delle sole note di credito collegate alle fatture attualmente filtrate
     * in tabella, usato dal totale della relativa colonna. Le note di debito non
     * rientrano nel conteggio: si comportano come fatture.
     */
    protected static function sumCreditNotes(QueryBuilder $query): float
    {
        return (float) PassiveInvoice::query()
            ->whereIn('parent_id', (clone $query)->reorder()->select('passive_invoices.id'))
            ->where('doc_type', 'TD04')
            ->sum('total');
    }

    public static function getRelations(): array
    {
        return [
            // PassiveItemsRelationManager::class,
            VariationNotesRelationManager::class,
            PassivePaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPassiveInvoices::route('/'),
            'create' => Pages\CreatePassiveInvoice::route('/create'),
            'edit' => Pages\EditPassiveInvoice::route('/{record}/edit'),
            'view' => Pages\ViewPassiveInvoice::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('supplier');
    }

    public static function saveSupplier(array $data, Supplier $supplier, Set $set): void
    {
        $supplier->company_id = Filament::getTenant()->id;

        $supplier->denomination = $data['denomination'] ?? null;
        $supplier->tax_code = $data['tax_code'] ?? null;
        $supplier->vat_code = $data['vat_code'] ?? null;

        $supplier->address = $data['address'] ?? null;
        $supplier->civic_number = $data['civic_number'] ?? null;
        $supplier->zip_code = $data['zip_code'] ?? null;
        $supplier->city = $data['city'] ?? null;
        $supplier->province = $data['province'] ?? null;
        $supplier->country = $data['country'] ?? null;

        $supplier->rea_office = $data['rea_office'] ?? null;
        $supplier->rea_number = $data['rea_number'] ?? null;
        $supplier->capital = $data['capital'] ?? null;
        $supplier->sole_share = $data['sole_share'] ?? null;
        $supplier->liquidation_status = $data['liquidation_status'] ?? null;

        $supplier->phone = $data['phone'] ?? null;
        $supplier->fax = $data['fax'] ?? null;
        $supplier->email = $data['email'] ?? null;
        $supplier->pec = null;

        $supplier->save();

        $set('supplier_id', $supplier->id);
        Notification::make()
            ->title('Fornitore salvato con successo')
            ->success()
            ->send();
    }
}
