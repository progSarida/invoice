<?php

namespace App\Filament\Company\Resources;

use App\Enums\PaymentMode;
use App\Enums\PaymentType;
use App\Enums\PiValidationStatus;
use App\Enums\SdiStatus;
use App\Filament\Company\Resources\PassiveInvoiceResource\Pages;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassiveItemsRelationManager;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers\PassivePaymentsRelationManager;
use App\Models\DocType;
use App\Models\PassiveInvoice;
use App\Models\Supplier;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PassiveInvoiceResource extends Resource
{
    protected static ?string $model = PassiveInvoice::class;

    public static ?string $pluralModelLabel = 'Fatture passive';

    public static ?string $modelLabel = 'Fattura passiva';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatture passive';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            // ->disabled()
            ->schema([
                // Grid::make('GRID')->columnSpan(2)->schema([

                    Placeholder::make('pi_validation')
                        ->label('')
                        // ->visible(fn($record) => $record && filled($record->pi_validation_id))
                        ->visible(fn($record) => $record )
                        ->content(function ($record) {
                            if (!$record->pi_validation_id) {
                                return 'Nessuna validazione selezionata';
                            }

                            return optional($record->piValidation)->name;
                        })
                        ->extraAttributes(function ($record) {
                            $statusEnum = $record?->piValidation?->pi_validation_status;

                            $color = $statusEnum?->getColor() ?? 'gray';

                            $bgColorClass = "bg-{$color}-100";

                            $borderColorClass = "border-{$color}-400";

                            $baseClasses = 'text-lg font-semibold border pb-1 pt-2';

                            $customClasses = [
                                'rounded-lg', // Arrotondamento angoli

                                'text-center', // Testo centrato

                                $bgColorClass, // Colore di sfondo dinamico
                                $borderColorClass,
                                'text-gray-900', // Assicura che il testo sia leggibile su sfondi chiari
                            ];

                            return [
                                'class' => $baseClasses . ' ' . implode(' ', $customClasses),
                            ];
                        })
                        ->columnSpan('full'),

                    Section::make('Riferimenti')
                        ->collapsible(false)
                        ->columns(6)
                        ->schema([

                            Forms\Components\Select::make('supplier_id')->label('Fornitore')
                                ->hintAction(
                                    ActionsAction::make('Nuovo')
                                        ->icon('ri-user-2-line')
                                        ->form(fn(Form $form) => SupplierResource::modalForm($form))
                                        ->modalWidth('7xl')
                                        ->modalHeading('')
                                        ->action(fn (array $data, Supplier $supplier, Set $set) => PassiveInvoiceResource::saveSupplier($data, $supplier, $set))
                                        ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Company\Resources\PassiveInvoiceResource\Pages\EditPassiveInvoice)
                                )
                                ->required()
                                ->columnSpan(3)
                                ->relationship('supplier', 'denomination')
                                //  ->disabled()
                                ,

                            Forms\Components\Select::make('parent_id')
                                ->label('Fattura')
                                ->columnSpan(3)
                                ->relationship('parent', 'denomination')
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => $record->number
                                )
                                ->visible(fn (Get $get) => !is_null($get('parent_id')))
                                //  ->disabled()
                                ,
                        ]),

                        Section::make('')
                        ->columns(12)
                        ->schema([
                            Forms\Components\Select::make('doc_type')
                                ->label('Tipo documento')
                                ->required()
                                ->columnSpan(7)
                                ->options(function (Get $get) {
                                    $docs = DocType::select('doc_types.name', 'doc_types.description')
                                        ->get();
                                    return $docs->pluck('description', 'name')->toArray();
                                })
                                //  ->disabled()
                                ,
                            Forms\Components\TextInput::make('number')
                                ->label('Numero')
                                ->required()
                                ->extraInputAttributes(['class' => 'text-right'])
                                ->columnSpan(3)
                                //  ->disabled()
                                ,
                            Forms\Components\DatePicker::make('invoice_date')
                                ->label('Data')
                                ->required()
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->columnSpan(2)
                                //  ->disabled()
                                ,

                            Forms\Components\Select::make('parent_id')
                                ->label('Fattura stornata')
                                ->visible(
                                    function (Get $get, $record) {
                                        $doc_type = $get('doc_type');
// dd($record, $docTypeId);
                                        if (!filled($doc_type)) {
                                            return false;
                                        }

                                        $docType = DocType::with('docGroup')->where('name', $doc_type)->first();

                                        return $docType?->docGroup?->name === 'Note di variazione' || $record?->parent_id;
                                        // return true;
                                    }
                                )
                                ->live()
                                ->relationship(
                                    name: 'parent',
                                    modifyQueryUsing:
                                        function (Builder $query, Get $get){
                                            $query->whereHas('docType.docGroup', function ($query) {
                                                    $query->whereIn('name', ['Fatture', 'Autofatture']);
                                                })
                                                ->where('supplier_id',$get('supplier_id'))
                                                ->orderBy('number','desc');
                                        }
                                )
                                ->getOptionLabelFromRecordUsing(
                                    function (Model $record) {
                                        $return = "Fattura n. {$record->number} del {$record->invoice_date->format('d/m/Y')} ";
                                        return $return;
                                    }
                                )
                                ->columnSpan(12),

                            Forms\Components\FileUpload::make('pdf_path')->label('File PDF')
                                ->required(fn ($record) => !$record?->pdf_path)
                                ->dehydrated(fn ($record) => !$record?->pdf_path)
                                // ->disk('public')
                                ->directory('passive_invoices/pdf_files')
                                // ->visibility('public')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(10240)
                                ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get) {
                                    $supplierId = $get('supplier_id') ?? 'unknown';
                                    $number = $get('number') ?? 'unknown';
                                    $invoiceDate = $get('invoice_date') ?? 'unknown';
                                    $extension = $file->getClientOriginalExtension();
                                    return sprintf('PDF_FAT_PASS_%s_%s_%s.%s', $supplierId, $number, $invoiceDate, $extension);
                                })
                                ->columnSpan(4),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('view_pdf')
                                    ->label('Visualizza pdf')
                                    ->icon('heroicon-o-eye')
                                    // ->url(fn($record): ?string => $record && $record->pdf_path ? Storage::url($record->pdf_path) : null)
                                    ->url(fn($record): ?string => $record->pdf_path ? Storage::temporaryUrl($record->pdf_path,now()->addMinutes(1)) : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn($record): bool => $record && $record->pdf_path)
                                    ->color('primary'),
                            ])
                            ->columnSpan(2),

                            Forms\Components\FileUpload::make('xml_path')->label('File XML')
                                // ->required(fn ($record) => !$record?->xml_path)
                                ->dehydrated(fn ($record) => !$record?->xml_path)
                                // ->disk('public')
                                ->directory('passive_invoices/xml_files')
                                // ->visibility('public')
                                ->acceptedFileTypes([
                                    'application/xml',
                                    'text/xml',
                                    'application/x-xml'
                                ])
                                ->maxSize(10240)
                                ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get) {
                                    $supplierId = $get('supplier_id') ?? 'unknown';
                                    $number = $get('number') ?? 'unknown';
                                    $invoiceDate = $get('invoice_date') ?? 'unknown';
                                    $extension = $file->getClientOriginalExtension();
                                    return sprintf('XML_FAT_PASS_%s_%s_%s.%s', $supplierId, $number, $invoiceDate, $extension);
                                })
                                ->columnSpan(4),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('view_xml')
                                    ->label('Visualizza xml')
                                    ->icon('heroicon-o-eye')
                                    // ->url(fn($record): ?string => $record && $record->xml_path ? Storage::url($record->xml_path) : null)
                                    ->url(fn($record): ?string => $record->xml_path ? Storage::temporaryUrl($record->xml_path,now()->addMinutes(1)) : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn($record): bool => $record && $record->xml_path)
                                    ->color('primary'),
                            ])
                            ->columnSpan(2),
                        ]),

                        Section::make('Dati per il pagamento')
                            ->collapsed(false)
                            ->columns(6)
                            ->schema([
                                Forms\Components\Select::make('payment_mode')
                                    ->label('Condizioni di pagamento')
                                    ->columnSpan(2)
                                    ->options(
                                        collect(PaymentMode::cases())
                                            ->sortBy(fn (PaymentMode $type) => $type->getOrder())
                                            ->mapWithKeys(fn (PaymentMode $type) => [
                                                $type->getCode() => $type->getLabel()
                                            ])
                                            ->toArray()
                                    )
                                    //  ->disabled()
                                    ,
                                Forms\Components\Select::make('payment_type')
                                    ->label('Metodo di pagamento')
                                    ->columnSpan(2)
                                    ->options(
                                        collect(PaymentType::cases())
                                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                                            ->mapWithKeys(fn (PaymentType $type) => [
                                                $type->getCode() => $type->getLabel()
                                            ])
                                            ->toArray()
                                    )
                                    //  ->disabled()
                                    ,
                                Forms\Components\DatePicker::make('payment_deadline')
                                    ->label('Scadenza pagamento')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->columnSpan(2)
                                    //  ->disabled()
                                    ,

                                Forms\Components\DatePicker::make('last_payment_date')
                                    ->label('Data ultimo pagamento')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->columnSpan(2)
                                    //  ->disabled()
                                    ,

                                Forms\Components\TextInput::make('total_payment')
                                    ->label('Totale pagato')
                                    ->extraInputAttributes(['class' => 'text-right'])
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : number_format(0, 2, ',', '.'))
                                    ->columnSpan(2)
                                    // ->visible(fn (Get $get) => !is_null($get('bank')))
                                    //  ->disabled()
                                    ,

                                Forms\Components\TextInput::make('bank')
                                    ->label('Istituto finanziario')
                                    ->columnSpan(3)
                                    //  ->disabled()
                                    ,
                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->columnSpan(3)
                                    // ->visible(fn (Get $get) => !is_null($get('iban')))
                                    //  ->disabled()
                                    ,
                            ]),


                    Section::make('Descrizione')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Textarea::make('description')
                                ->label('')
                                ->columnSpanFull()
                                //  ->disabled()
                                ,
                        ]),
                // ]),
                // Grid::make('GRID')->columnSpan(3)->schema([
                    Section::make('Status SDI')
                            ->collapsed(false)
                            ->columns(6)
                            ->schema([
                                Forms\Components\TextInput::make('sdi_status')
                                    ->label('Status')
                                    ->columnSpan(3)
                                    //  ->disabled()
                                    ,
                                Forms\Components\TextInput::make('sdi_code')
                                    ->label('Codice SDI')
                                    ->columnSpan(3)
                                    //  ->disabled()
                                    ,
                            ]),
                // ]),

            // ])->columns(5);
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('docType.description')
                    ->label('Tipo documento')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->docType->description)
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.denomination')
                    ->label('Fornitore')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->supplier->denomination)
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->wrap()
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->description)
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Dovuto')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('payment_deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                // Tables\Columns\IconColumn::make('sdi_status')
                //     ->label('Stato')
                //     // ->tooltip(fn ($state): string => $state)
                //     ->sortable(),
                Tables\Columns\IconColumn::make('piValidation.pi_validation_status')
                    ->label('Validazione')
                    ->default(\App\Enums\PiValidationStatus::NO_STATUS)
                    ->tooltip(fn ($record): string => $record->piValidation ? $record->piValidation->name : 'Non validata')
                    ->sortable(),
                TextColumn::make('total_payment')
                    ->label('Pagato')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
            ])
            ->filtersFormWidth('2xl')
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('pi_validation_status')
                    ->label('Validazione')
                    ->columnSpan(1)
                    // ->options(PiValidationStatus::class)
                    ->options(fn () => [
                            'validati' => 'Tutti validati',   // La tua opzione custom
                        ] + PiValidationStatus::class::toArray()
                    )
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;

                        switch($value){
                            case PiValidationStatus::NO_STATUS->value:
                                return $query->whereNull('pi_validation_id');
                                break;
                            case PiValidationStatus::OK->value:
                            case PiValidationStatus::WAIT->value:
                            case PiValidationStatus::BLOCK->value:
                            case PiValidationStatus::VIEW->value:
                                return $query->whereHas('piValidation', function ($q) use ($value) {
                                        $q->where('pi_validation_status', $value);
                                    });
                                break;
                            case 'validati':
                                return $query->whereNotNull('pi_validation_id');
                                break;
                            default:
                                return $query;
                                break;
                        }
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('paid')
                    ->label('Pagamento')
                    ->columnSpan(1)
                    ->options([
                        'si' => 'Totale',
                        'par' => 'Parziale',
                        'no' => 'Nessuno',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        return $query->when($data['value'] === 'si', function ($q) {
                                return $q->whereRaw(" total_payment = total ");
                            })->when($data['value'] === 'par', function ($q) {
                                return $q->whereRaw(" total_payment < total and total_payment != 0.00 ");
                            })->when($data['value'] === 'no', function ($q) {
                                return $q->whereRaw(" total_payment = 0.00 ");
                            });
                    })
                    ->preload(),
                Filter::make('invoice_date_range')
                    ->columnSpan(2)
                    ->columns(2)
                    ->form([
                        DatePicker::make('invoice_from_date')
                            ->label('Data fattura da')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->columnSpan(1),
                        DatePicker::make('invoice_to_date')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->label('Data fattura a')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['invoice_from_date'])) {
                            $query->whereDate('invoice_date', '>=', $data['invoice_from_date']);
                        }
                        if (! empty($data['invoice_to_date'])) {
                            $query->whereDate('invoice_date', '<=', $data['invoice_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['invoice_from_date'] && $data['invoice_to_date']) {
                            return "Data fattura dal " . Carbon::parse($data['invoice_from_date'])->format('d/m/Y') . " al " . Carbon::parse($data['invoice_to_date'])->format('d/m/Y');
                        }
                        if ($data['invoice_from_date']) {
                            return "Data fattura dal " . Carbon::parse($data['invoice_from_date'])->format('d/m/Y');
                        }
                        if ($data['invoice_to_date']) {
                            return "Data fattura al " . Carbon::parse($data['invoice_to_date'])->format('d/m/Y');
                        }
                        return null;
                    }),
                SelectFilter::make('notes')
                    ->label('Note di credito')
                    ->columnSpan(1)
                    ->placeholder('Includi')
                    ->options([
                        'no' => 'Escludi',
                        'si' => 'Seleziona',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        return $query->when($data['value'] === 'si', function ($q) {
                                return $q->where('doc_type', 'TD04');
                            })->when($data['value'] === 'no', function ($q) {
                                return $q->where('doc_type', '!=', 'TD04');
                            });
                    })
                    ->default('no')
                    ->preload(),
                SelectFilter::make('autos')
                    ->label('Autofatture')
                    ->columnSpan(1)
                    ->placeholder('Includi')
                    ->options([
                        'no' => 'Escludi',
                        'si' => 'Seleziona',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (!$value) return $query;

                        $filterGroup = function ($q) {
                            $q->whereHas('docGroup', fn ($qGroup) => $qGroup->where('name', 'Autofatture'));
                        };

                        return $query->when(
                            $value === 'si',                                                            // Tutte
                            fn ($q) => $q->whereHas('docType', $filterGroup),                           // Solo autofatture
                            fn ($q) => $q->whereDoesntHave('docType', $filterGroup)                     // Esclude autofatture
                        );
                    })
                    ->default('no')
                    ->preload(),
                Filter::make('total_range')
                    ->columnSpan(2)
                    ->columns(2)
                    ->form([
                        TextInput::make('total_from')
                            ->label('Totale da')
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->columnSpan(1),
                        TextInput::make('total_to')
                            ->label('Totale a')
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['total_from'])) {
                            $query->where('total_doc', '>=', $data['total_from']);
                        }
                        if (! empty($data['total_to'])) {
                            $query->where('total_doc', '<=', $data['total_to']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['total_from'] && $data['total_to']) {
                            return "Importo da " . number_format($data['total_from'], 2, ',', '.') . " fino a " . number_format($data['total_to'], 2, ',', '.');
                        }
                        if ($data['total_from']) {
                            return "Importo da " . number_format($data['total_from'], 2, ',', '.');
                        }
                        if ($data['total_to']) {
                            return "Importo fino a " . number_format($data['total_to'], 2, ',', '.');
                        }
                        return null;
                    }),
            ])
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF')
                    ->icon('phosphor-file-pdf-duotone')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->pdf_path ? Storage::temporaryUrl($record->pdf_path,now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    // ->action(function ($record) {
                    //     $pdfPath = $record->pdf_path;
                    //     if ($pdfPath && Storage::disk('public')->exists($pdfPath)) {
                    //         return response()->download(
                    //             Storage::disk('public')->path($pdfPath),
                    //             'document_' . $record->number . '.pdf'
                    //         );
                    //     }
                    //     return redirect()->back()->with('error', 'File PDF non trovato.');
                    // })
                    ->visible(fn($record) => $record && $record->pdf_path)
                    // ->visible(function ($record) {
                    //     $visible = !empty($record->pdf_path) && Storage::disk('public')->exists($record->pdf_path);
                    //     return $visible;
                    // })
                    ,
                Action::make('download_xml')
                    ->label('')
                    ->tooltip('Scarica XML')
                    ->icon('tabler-file-type-xml')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->xml_path ? Storage::temporaryUrl($record->xml_path,now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    // ->action(function ($record) {
                    //     $xmlPath = $record->xml_path;
                    //     if ($xmlPath && Storage::disk('public')->exists($xmlPath)) {
                    //         return response()->download(
                    //             Storage::disk('public')->path($xmlPath),
                    //             'document_' . $record->number . '.xml'
                    //         );
                    //     }
                    //     return redirect()->back()->with('error', 'File XML non trovato.');
                    // })
                    ->visible(fn($record) => $record && $record->xml_path)
                    // ->visible(function ($record) {
                    //     $visible = !empty($record->xml_path) && Storage::disk('public')->exists($record->xml_path);
                    //     return $visible;
                    // })
                    ,
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()->isManager()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PassiveItemsRelationManager::class,
            PassivePaymentsRelationManager::class
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
