<?php

namespace App\Filament\Company\Resources;

use App\Filament\Exports\NewInvoiceExporter;
use App\Models\ContractDetail;
use Carbon\Carbon;
use Filament\Support\Colors\Color;
use Filament\Tables;
use App\Models\Client;
use App\Models\DocType;
use App\Models\Invoice;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Enums\SdiStatus;
use Filament\Forms\Form;
use App\Models\Sectional;
use Filament\Tables\Table;
use App\Models\NewContract;
use App\Enums\ReversalGroupType;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Resource;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Company\Resources\NewInvoiceResource\Pages;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\CreditNotesRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\InvoiceItemsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\ActivePaymentsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\SdiNotificationsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\AttachmentsSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\BillingTimingSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\DescriptionsSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\DocumentSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\OptionsSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\PaymentDataSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\PaymentStatusSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\RecipientSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\SdiStatusSection;
use App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections\TotalsSection;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\DocumentFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\NumberAndDateFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\PaymentDataFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\PaymentStatusFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\RecipientFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\RegistrationFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\TotalFilters;
use App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters\YearFilters;
use App\Models\ReversalMotivationType;
use Filament\Forms\Components\Livewire;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NewInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static ?string $pluralModelLabel = 'Fatture';

    public static ?string $modelLabel = 'Fattura';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    protected static ?int $navigationSort = 1;

    protected static ?int $navigationGroupSort = 2;

    public static function form(Form $form): Form
    {
        // 1. Definiamo la subquery per trovare l'ultima data di dettaglio per ogni contratto
        // Lo facciamo fuori dalle closure principali per riutilizzarlo e per chiarezza
        $latestDetailSubquery = \App\Models\ContractDetail::query()
            ->selectRaw('contract_id, MAX(date) as latest_detail_date')
            ->groupBy('contract_id')
            ->toBase();

        return $form
            ->schema([

                Toggle::make('level')                                       //
                    ->label('Quadratura saldi')                             //
                    ->dehydrated(false)                                     // Flag quadratura saldi (temporaneo)
                    ->hidden(false)                                         //
                    ->live(),                                               //

                OptionsSection::make(),                                     // Opzioni: art. 73, cassa previdenziale, ritenute
                RecipientSection::make($latestDetailSubquery),              // Destinatario/Cliente
                BillingTimingSection::make(),                               // Tempistica
                DocumentSection::make(),                                    // Dati documento

                Livewire::make(                                             // 
                    InvoiceItemsRelationManager::class,                     // 
                    fn (?Invoice $record, $livewire) => [                   // 
                        'ownerRecord' => $record,                           // 
                        'pageClass'   => $livewire::class,                  // Voci documento
                    ],                                                      // 
                )                                                           // 
                ->visible(fn (?Invoice $record) => $record !== null)        // 
                ->key('rm-invoice-items')                                   // 
                ->columnSpanFull(),                                         // 

                DescriptionsSection::make(),                                // Descrizioni
                TotalsSection::make(),                                      // Totali
                PaymentDataSection::make(),                                 // Dati pagamento
                SdiStatusSection::make(),                                   // Stato SDI
                PaymentStatusSection::make(),                               // Stato pagamenti
                AttachmentsSection::make(),                                 // Allegati

            ]);

    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->query(Invoice::newInvoices())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('🔍 Id')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('docType.description')->label('Tipo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')->label('Numero')
                    ->formatStateUsing(function ( Invoice $invoice) {
                        return $invoice->getNewInvoiceNumber();
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('year', $direction)
                            ->orderBy('sectional_id', $direction)
                            ->orderBy('number', $direction);
                    }),
                Tables\Columns\TextColumn::make('invoice_date')->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('description')->label('🔍 Descrizione')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    // ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('invoice.id')->label('Fattura stornata')
                    ->formatStateUsing(function ( string $state ) {
                        $invoice = Invoice::find($state);
                        return $invoice->getNewInvoiceNumber();
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parent_id')->label('Id fattura stornata')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract.cig_code')->label('CIG')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract.cup_code')->label('CUP')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract.rdo_code')->label('RDO')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tax_type')->label('Entrata')
                    // ->badge()
                    ->color('black')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('no_vat_total')->label('Imponibile')
                //     ->money('EUR')
                //     ->sortable()
                //     // ->state(fn (Invoice $invoice) => $invoice->getTaxable())
                //     ->alignRight()
                //     ->summarize([
                //         Tables\Columns\Summarizers\Summarizer::make()
                //             ->label('')
                //             ->using(function ($query) {
                //                 // Eseguiamo la query forzando i modelli Invoice ed eager-caricando docType
                //                 $records = Invoice::query()
                //                     ->with('docType')
                //                     ->whereIn('id', $query->pluck('id'))
                //                     ->get();

                //                 $sum = 0;
                //                 foreach ($records as $record) {
                //                     if ($record->docType?->name == 'TD04') {
                //                         $sum -= $record->no_vat_total;
                //                     } else {
                //                         $sum += $record->no_vat_total;
                //                     }
                //                 }

                //                 return $sum;
                //             })
                //             ->money('EUR', true, 'it_IT'),
                //     ])
                //     ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('no_vat_total')->label('Imponibile')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function (QueryBuilder $query) {
                                return (clone $query)
                                    ->reorder()
                                    ->join('doc_types', 'invoices.doc_type_id', '=', 'doc_types.id')
                                    ->select(DB::raw("SUM(CASE WHEN doc_types.name = 'TD04' THEN -invoices.no_vat_total ELSE invoices.no_vat_total END) as tot"))
                                    ->value('tot');
                            })
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('vat')->label('IVA')
                //     ->money('EUR')
                //     // ->state(fn (Invoice $invoice) => $invoice->getVat())
                //     ->sortable()
                //     ->alignRight()
                //     ->summarize([
                //         Tables\Columns\Summarizers\Summarizer::make()
                //             ->label('')
                //             ->using(function ($query) {
                //                 // Eseguiamo la query forzando i modelli Invoice ed eager-caricando docType
                //                 $records = Invoice::query()
                //                     ->with('docType')
                //                     ->whereIn('id', $query->pluck('id'))
                //                     ->get();

                //                 $sum = 0;
                //                 foreach ($records as $record) {
                //                     if ($record->docType?->name == 'TD04') {
                //                         $sum -= $record->no_vat_total;
                //                     } else {
                //                         $sum += $record->no_vat_total;
                //                     }
                //                 }

                //                 return $sum;
                //             })
                //             ->money('EUR', true, 'it_IT'),
                //     ])
                //     ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('vat')->label('IVA')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function (QueryBuilder $query) {
                                return (clone $query)
                                    ->reorder()
                                    ->join('doc_types', 'invoices.doc_type_id', '=', 'doc_types.id')
                                    ->select(DB::raw("SUM(CASE WHEN doc_types.name = 'TD04' THEN -invoices.vat ELSE invoices.vat END) as tot"))
                                    ->value('tot');
                            })
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('total')->label('Totale')
                //     ->money('EUR')
                //     ->sortable()
                //     ->alignRight()
                //     ->summarize([
                //         Tables\Columns\Summarizers\Summarizer::make()
                //             ->label('')
                //             ->using(function ($query) {
                //                 // Eseguiamo la query forzando i modelli Invoice ed eager-caricando docType
                //                 $records = Invoice::query()
                //                     ->with('docType')
                //                     ->whereIn('id', $query->pluck('id'))
                //                     ->get();

                //                 $sum = 0;
                //                 foreach ($records as $record) {
                //                     if ($record->docType?->name == 'TD04') {
                //                         $sum -= $record->no_vat_total;
                //                     } else {
                //                         $sum += $record->no_vat_total;
                //                     }
                //                 }

                //                 return $sum;
                //             })
                //             ->money('EUR', true, 'it_IT'),
                //     ])
                //     // ->tooltip(fn (Invoice $record) => $record->total . " - " . "(" . $record->total_payment . " + " . $record->total_notes . ")" . " = " . $record->total-($record->total_payment+$record->total_notes))
                //     ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total')->label('Totale')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function (QueryBuilder $query) {
                                return (clone $query)
                                    ->reorder()
                                    ->join('doc_types', 'invoices.doc_type_id', '=', 'doc_types.id')
                                    ->select(DB::raw("SUM(CASE WHEN doc_types.name = 'TD04' THEN -invoices.total ELSE invoices.total END) as tot"))
                                    ->value('tot');
                            })
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total_notes')->label('Note di credito')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('tot_own')->label('Totale a doversi')
                //     ->money('EUR')
                //     ->state(fn (Invoice $invoice) => $invoice->docType?->name == 'TD04' ? 0.00 : $invoice->getOwned())
                //     ->sortable()
                //     ->alignRight()
                //     ->summarize([
                //         Tables\Columns\Summarizers\Summarizer::make()
                //             ->label('')
                //             ->using(function ($query) {
                //                 // Forza il recupero come Collection di modelli Invoice
                //                 return Invoice::query()
                //                     ->whereIn('id', $query->pluck('id'))
                //                     ->get()
                //                     ->sum(fn (Invoice $invoice) => $invoice->docType?->name == 'TD04' ? 0.00 : $invoice->getOwned());
                //             })
                //             ->money('EUR', true, 'it_IT'),
                //     ])
                //     ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('tot_own')->label('Totale a doversi')
                    ->money('EUR')
                    ->state(fn (Invoice $invoice) => $invoice->docType?->name == 'TD04' ? 0.00 : $invoice->getOwned())
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function (QueryBuilder $query) {
                                return (clone $query)
                                    ->reorder()
                                    ->join('doc_types', 'invoices.doc_type_id', '=', 'doc_types.id')
                                    ->leftJoinSub(
                                        \App\Models\Invoice::query()
                                            ->join('doc_types as cn_doc_types', 'invoices.doc_type_id', '=', 'cn_doc_types.id')
                                            ->where('cn_doc_types.name', 'TD04')
                                            ->whereNotNull('invoices.parent_id')
                                            ->selectRaw('invoices.parent_id, SUM(invoices.no_vat_total) as cn_no_vat, SUM(invoices.vat) as cn_vat')
                                            ->groupBy('invoices.parent_id'),
                                        'credit_notes',
                                        'credit_notes.parent_id', '=', 'invoices.id'
                                    )
                                    ->select(DB::raw("
                                        SUM(
                                            CASE WHEN doc_types.name = 'TD04' THEN 0
                                            ELSE
                                                CASE WHEN invoices.is_total_with_vat
                                                    THEN (invoices.no_vat_total - COALESCE(credit_notes.cn_no_vat, 0))
                                                    + (invoices.vat - COALESCE(credit_notes.cn_vat, 0))
                                                    ELSE (invoices.no_vat_total - COALESCE(credit_notes.cn_no_vat, 0))
                                                END
                                            END
                                        ) as tot
                                    "))
                                    ->value('tot');
                            })
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total_payment')->label('Pagamenti')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('tot_res')->label('Residuo')
                //     ->money('EUR')
                //     ->state(fn (Invoice $invoice) => $invoice->docType?->name == 'TD04' ? 0.00 : $invoice->getResidue())
                //     ->sortable()
                //     ->alignRight()
                //     ->summarize([
                //         Tables\Columns\Summarizers\Summarizer::make()
                //             ->label('')
                //             ->using(function ($query) {
                //                 // Forza il recupero come Collection di modelli Invoice
                //                 return Invoice::query()
                //                     ->whereIn('id', $query->pluck('id'))
                //                     ->get()
                //                     ->sum(fn (Invoice $invoice) => $invoice->docType?->name == 'TD04' ? 0.00 : $invoice->getResidue());
                //             })
                //             ->money('EUR', true, 'it_IT'),
                //     ])
                //     ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('tot_res')->label('Residuo')
                    ->money('EUR')
                    ->state(fn (Invoice $invoice) => $invoice->docType?->name == 'TD04' ? 0.00 : $invoice->getResidue())
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(function (QueryBuilder $query) {
                                return (clone $query)
                                    ->reorder()
                                    ->join('doc_types', 'invoices.doc_type_id', '=', 'doc_types.id')
                                    ->leftJoinSub(
                                        \App\Models\Invoice::query()
                                            ->join('doc_types as cn_doc_types', 'invoices.doc_type_id', '=', 'cn_doc_types.id')
                                            ->where('cn_doc_types.name', 'TD04')
                                            ->whereNotNull('invoices.parent_id')
                                            ->selectRaw('invoices.parent_id, SUM(invoices.no_vat_total) as cn_no_vat, SUM(invoices.vat) as cn_vat')
                                            ->groupBy('invoices.parent_id'),
                                        'credit_notes',
                                        'credit_notes.parent_id', '=', 'invoices.id'
                                    )
                                    ->select(DB::raw("
                                        SUM(
                                            CASE WHEN doc_types.name = 'TD04' THEN 0
                                            ELSE
                                                CASE WHEN invoices.is_total_with_vat
                                                    THEN (invoices.no_vat_total - COALESCE(credit_notes.cn_no_vat, 0) - invoices.total_payment)
                                                    + (invoices.vat - COALESCE(credit_notes.cn_vat, 0))
                                                    ELSE (invoices.no_vat_total - COALESCE(credit_notes.cn_no_vat, 0) - invoices.total_payment)
                                                END
                                            END
                                        ) as tot
                                    "))
                                    ->value('tot');
                            })
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('sdi_status')->label('Stato')
                //     ->searchable()
                //     // ->badge()
                //     ->color('black')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('sdi_status')->label('Stato')
                    ->tooltip(fn (SdiStatus $state): string => $state->getLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('sdi_date')->label('Data status')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')->label('Registrata da')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                ...DocumentFilters::make(),
                ...RecipientFilters::make(),
                ...NumberAndDateFilters::make(),
                ...PaymentStatusFilters::make(),
                ...YearFilters::make(),
                ...TotalFilters::make(),
                ...PaymentDataFilters::make(),
                ...RegistrationFilters::make(),
            ],layout: FiltersLayout::Dropdown)->filtersFormColumns(24)->filtersFormWidth(MaxWidth::SevenExtraLarge)
            // ],layout: FiltersLayout::Modal)->filtersFormColumns(12)->filtersFormWidth(MaxWidth::SevenExtraLarge)
            // ])->filtersFormColumns(12)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF (formato AssoSoftware)')
                    ->icon('phosphor-file-pdf-duotone')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->pdf_path ? Storage::temporaryUrl($record->pdf_path, now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        // Aggiungi il controllo che pdf_path non sia null/vuoto
                        return $record &&
                            !empty($record->pdf_path) &&
                            Storage::disk(config('filesystems.default'))->exists($record->pdf_path);
                    }),

                Tables\Actions\Action::make('download_xml')
                    ->label('')
                    ->tooltip('Scarica XML')
                    ->icon('tabler-file-type-xml')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->xml_path ? Storage::temporaryUrl($record->xml_path, now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        // Aggiungi il controllo che xml_path non sia null/vuoto
                        return $record &&
                            !empty($record->xml_path) &&
                            Storage::disk(config('filesystems.default'))->exists($record->xml_path);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
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
                                        Blade::render('pdf.new_invoices', [
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
                        ->exporter(NewInvoiceExporter::class)
                        ->color(Color::rgb('rgb(0, 153, 0)'))
                        ->icon('phosphor-file-xls-duotone'),
                        // ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // InvoiceItemsRelationManager::class,
            SdiNotificationsRelationManager::class,
            CreditNotesRelationManager::class,
            ActivePaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewInvoices::route('/'),
            'create' => Pages\CreateNewInvoice::route('/create'),
            'edit' => Pages\EditNewInvoice::route('/{record}/edit'),
            'view' => Pages\ViewNewInvoice::route('/{record}'),
        ];
    }

    // public static function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['flow'] = 'out';
    //     return $data;
    // }

    public static function saveClient(array $data, Client $client, Get $get, Set $set): void
    {
        // dd($data);
        $client->company_id = Filament::getTenant()->id;
        $client->type = $data['type'] ?? null;
        $client->subtype = $data['subtype'] ?? null;
        $client->denomination = $data['denomination'] ?? null;
        $client->state_id = $data['state_id'] ?? null;
        $client->address = $data['address'] ?? null;
        $client->zip_code = $data['zip_code'] ?? null;
        $client->city_id = $data['city_id'] ?? null;
        $client->place = $data['place'] ?? null;
        $client->tax_code = $data['tax_code'] ?? null;
        $client->vat_code = $data['vat_code'] ?? null;
        $client->ipa_code = $data['ipa_code'] ?? null;
        $client->phone = $data['phone'] ?? null;
        $client->email = $data['email'] ?? null;
        $client->pec = $data['pec'] ?? null;
        $client->save();

        $set('client_id', $client->id);

        if ($client && $client->type) {
            $sectional = Sectional::where('company_id', Filament::getTenant()->id)
                ->where('client_type', $client->type->value)
                ->first();
            if ($sectional) {
                $set('sectional_id', $sectional->id);
                $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                $set('number', $number);
                NewInvoiceResource::invoiceNumber($get, $set);
            } else {
                $set('sectional_id', null);
                $set('number', null);
                NewInvoiceResource::invoiceNumber($get, $set);
                Notification::make()
                    ->title('Nessun sezionario trovato per il tipo di cliente selezionato.')
                    ->warning()
                    ->send();
            }
        }


        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }

    public static function saveContract(array $data, NewContract $contract, Set $set): void
    {
        $contract->company_id = Filament::getTenant()->id;
        $contract->client_id = $data['client_id'];
        // $contract->tax_types = $data['tax_types'];
        $contract->setTaxTypesAttribute($data['tax_types']);
        $contract->start_validity_date = $data['start_validity_date'];
        $contract->end_validity_date = $data['end_validity_date'];
        // $contract->accrual_types = $data['accrual_types'];
        $contract->setAccrualTypesAttribute($data['accrual_types']);
        $contract->payment_type = $data['payment_type'];
        $contract->cig_code = $data['cig_code'];
        $contract->cup_code = $data['cup_code'];
        $contract->office_code = $data['office_code'];
        $contract->office_name = $data['office_name'];
        $contract->amount = $data['amount'];
        $contract->invoicing_cycle = $data['invoicing_cycle'] ?? null;
        $contract->new_contract_copy_path = $data['new_contract_copy_path'] ?? null;
        $contract->new_contract_copy_date = $data['new_contract_copy_date'] ?? null;
        $contract->reinvoice = $data['reinvoice'] ?? false;
        $contract->reinvoice_type = $data['reinvoice_type'] ?? false;
        $contract->save();
        $set('contract_id', $contract->id);
        Notification::make()
            ->title('Contratto salvato con successo')
            ->success()
            ->send();
    }

    public static function saveDetail(array $data, ContractDetail $detail, $contract_id): void
    {
        $detail->contract_id = $contract_id;
        $detail->number = $data['number'];
        $detail->contract_type = $data['contract_type'];
        $detail->date = $data['date'];
        $detail->description = $data['description'];
        $detail->invoice_description = $data['invoice_description'];
        $detail->contract_attachment_path = $data['contract_attachment_path'];
        $detail->contract_attachment_date = $data['contract_attachment_date'];

        $detail->save();

        Notification::make()
            ->title('Dettaglio contratto salvato con successo')
            ->success()
            ->send();
    }

    public static function invoiceNumber(Get $get, Set $set){

        if($get('art_73')) {
            $number = "";
            $date = $get('invoice_date');
            for($i=strlen($get('number'));$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$get('number');
            $set('invoice_uid', $number."/".$date);
        }
        else if(empty($get('number')) || empty($get('sectional_id')) || empty($get('year')))
            $set('invoice_uid', null);
        else{
            $number = "";
            $sectional = Sectional::find($get('sectional_id'))->description;
            for($i=strlen($get('number'));$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$get('number');
            $set('invoice_uid', $number."/".$sectional."/".$get('year'));
        }

    }

    public static function calculateNextInvoiceNumber(Get $get): ?int
    {
        $year = $get('year');
        $sectionalId = $get('sectional_id');
        $art73 = $get('art_73');
        $invoiceDate = $get('invoice_date');

        if ($art73) {
            $maxNumber = Invoice::where('invoice_date', $invoiceDate)
                ->where('art_73', true)
                ->where('company_id', Filament::getTenant()->id)
                ->max('number');

            if ($maxNumber !== null) {
                return $maxNumber + 1;
            }

            return 1;
        }
        else if ($year && $sectionalId) {
            $maxNumber = Invoice::where('year', $year)
                ->where('sectional_id', $sectionalId)
                ->where('company_id', Filament::getTenant()->id)
                ->max('number');

            if ($maxNumber !== null) {
                return $maxNumber + 1;
            }

            $sectional = Sectional::find($sectionalId);
            return $sectional?->progressive;
        }

        return null;
    }

    public static function updateDescription(Get $get, Set $set, $new): void
    {
        $description = '';
        $docTypeId = $get('doc_type_id');
        $year = substr($get('budget_year'), 2);

        if (filled($docTypeId)) {

            $docType = DocType::with('docGroup')->find($docTypeId);

            if($docType->name == 'TD99'){
                $set('description', '(ab01) Quadratura saldi per €');
                return;
            } else if ($docType?->docGroup?->name === 'Note di variazione') {
                if($new === 'new_doc'){
                    $set('description', '');
                }
                if($new === 'new_ref'){
                    $set('reference_date_from', '');
                    $set('reference_date_to', '');
                    $set('reference_number_from', '');
                    $set('reference_number_to', '');
                    $set('total_number', '');
                }

                $docType = DocType::find($get('doc_type_id'))->description;
                $description = '(ab' . $year .') ' . $docType;

                $reversalGroupType = ReversalGroupType::tryFrom($get('reversal_group_type'))?->getLabel();
                if($reversalGroupType)
                    $description .= ' a storno ' . lcfirst($reversalGroupType);

                $parent = Invoice::find($get('parent_id'));
                if($parent){
                    $description .= ' della ' . lcfirst($parent?->docType->description);
                    $description .= ' n.ro ' . $parent?->getNewInvoiceNumber();
                    $description .= ' del ' . Carbon::parse($parent?->invoice_date)->format('d/m/Y');

                    $motivation = ReversalMotivationType::find($get('reversal_motivation_type_id'))?->name;
                    if($motivation)
                        $description .= ' per ' . lcfirst($motivation) . '.';
                }
            } else {
                if($new === 'new_doc'){
                    $set('description', '');
                    $set('reference_date_from', '');
                    $set('reference_date_to', '');
                    $set('reference_number_from', '');
                    $set('reference_number_to', '');
                    $set('total_number', '');
                }
                if($new === 'new_ref'){
                    $set('reference_date_from', '');
                    $set('reference_date_to', '');
                    $set('reference_number_from', '');
                    $set('reference_number_to', '');
                    $set('total_number', '');
                }

                $contractDescription = NewContract::find($get('contract_id'))?->lastDetail?->invoice_description ?? '';

                $description = '(ab' . $year .') ' . $contractDescription . ' ';

                // $description .= 'Corrispettivo per ' . strtolower($accrualType) . ' ';

                $invoiceReference = $get('invoice_reference');
                if ($invoiceReference) {
                    $dateFrom = $get('reference_date_from');
                    $dateTo = $get('reference_date_to');
                    if ($dateFrom) {
                        $description .= 'per il periodo dal ' . static::formatDate($dateFrom);

                        if ($dateTo) {
                            $description .= ' al ' . static::formatDate($dateTo);
                        }
                    }

                    $numberFrom = $get('reference_number_from');
                    $numberTo = $get('reference_number_to');
                    if ($numberFrom) {
                        $description .= 'dal verbale numero ' . $numberFrom;

                        if ($numberTo) {
                            $description .= ' al verbale numero ' . $numberTo;

                            $total = $get('reference_number_to') - $get('reference_number_from') + 1;
                            $set('total_number', $total);
                            if ($total) {
                                $description .= ' per un totale di ' . $total . ' verbali';
                            }
                        }
                    }


                }
            }

            // $set('description', trim($description));
        }

        $set('description', trim($description));
    }

    protected static function formatDate($date): string
    {
        if (is_string($date)) {
            return Carbon::parse($date)->format('d/m/Y');
        }

        if ($date instanceof Carbon || $date instanceof \DateTime) {
            return $date->format('d/m/Y');
        }

        return (string) $date;
    }
}
