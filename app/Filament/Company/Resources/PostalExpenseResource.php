<?php

namespace App\Filament\Company\Resources;

use App\Enums\ReinvoiceType;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\AttachmentsSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\BaseInfoSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\ExpenseSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\NoteSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\NotificationSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\PaymentSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\ReinvoiceSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\ShipmentSection;
use App\Filament\Company\Resources\PostalExpenseResource\Pages;
use App\Services\CurrencyService;
use Filament\Resources\Resource;
use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\ShipmentDocType;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PassiveInvoice;
use App\Models\PostalExpense;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use function PHPUnit\Framework\isNull;

class PostalExpenseResource extends Resource
{
    protected static ?string $model = PostalExpense::class;

    public static ?string $pluralModelLabel = 'Spese di notifica';

    public static ?string $modelLabel = 'Spesa di notifica';

    protected static ?string $navigationIcon = 'tabler-mail-dollar';

    protected static ?string $navigationGroup = 'Costi di notifica';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'denomination';

    public static function form(Form $form): Form
    {
        // 1. Definiamo la subquery per trovare l'ultima data di dettaglio per ogni contratto
        // Lo facciamo fuori dalle closure principali per riutilizzarlo e per chiarezza
        $latestDetailSubquery = \App\Models\ContractDetail::query()
            ->selectRaw('contract_id, MAX(date) as latest_detail_date')
            ->groupBy('contract_id')
            ->toBase();

        return $form->schema([
            BaseInfoSection::make($latestDetailSubquery),
            ShipmentSection::make(),
            NotificationSection::make(),
            ExpenseSection::make(),
            PaymentSection::make(),
            ReinvoiceSection::make(),
            NoteSection::make(),
            AttachmentsSection::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('order_rif')
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('Cliente')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_type')
                    // ->badge()
                    ->label('Entrata'),
                Tables\Columns\TextColumn::make('manage_year')
                    ->label('🔍 Anno')
                    ->searchable(),
                Tables\Columns\TextColumn::make('actType.name')
                    // ->badge()
                    ->label('Tipo atto'),
                Tables\Columns\TextColumn::make('act_date')
                    // ->badge()
                    ->date('d/m/Y')
                    ->label('Data atto'),
                Tables\Columns\TextColumn::make('counterpart')
                    ->label('Controparte')
                    ->getStateUsing(function ($record) {
                        $counterpart = "";
                        if($record->supplier_id)
                            $counterpart = Supplier::find($record->supplier_id)->denomination;
                        else
                            $counterpart = $record->supplier_name;
                        return $counterpart;
                    })
                    ->limit(20),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo da rimborsare')
                    ->getStateUsing(function ($record) {
                        $sum = ($record->notify_amount ?? 0) +
                            // ($record->notify_expense_amount ?? 0) +
                            ($record->mark_expense_amount ?? 0);
                        return $sum;
                    })
                    ->alignRight()
                    ->money('EUR'),
                // Tables\Columns\IconColumn::make('reinvoice')
                //     ->label('Rifatturare')
                //     ->boolean(),
                Tables\Columns\TextColumn::make('reinvoice_type')
                    // ->badge()
                    ->label('Tipo rifatturazione'),
                Tables\Columns\IconColumn::make('reinvoiced')
                    ->label('Rifatturato')
                    ->getStateUsing(function ($record) {
                        $reinvoice = Invoice::find($record->reinvoice_id);
                        return !is_null($reinvoice);
                    })
                    ->boolean(),
            ])
            ->filters([
                // SEZIONE: Identificazione
                Tables\Filters\Filter::make('identificazione')
                    ->form([
                        Forms\Components\Section::make('Informazioni di base per l\'identificazione della spesa postale')
                            ->collapsed()
                            ->columns(12)
                            ->schema([
                                Forms\Components\Select::make('client_id')
                                    ->label('Cliente')
                                    // ->relationship(name: 'client', titleAttribute: 'denomination')
                                    ->getSearchResultsUsing(function (string $search) {
                                        // Rimuovi spazi multipli e trim
                                        $search = trim(preg_replace('/\s+/', ' ', $search));

                                        // Query base con le stesse condizioni del relationship
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
                                            // Un solo valore: cerca SOLO match esatto in number o year
                                            $value = $parts[0];
                                            $query->where(function ($q) use ($value) {
                                                $q->where('denomination', 'LIKE', "%{$value}%");
                                            });
                                        }

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
                                    ->getOptionLabelFromRecordUsing(
                                        // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                        fn (Model $record) => $record->denomination
                                    )
                                    ->searchable()
                                    ->placeholder('')
                                    ->preload()
                                    ->columnSpan(4),
                                Forms\Components\Select::make('notify_type')
                                    ->label('Tipo notifica')
                                    ->options(NotifyType::class)
                                    ->searchable()
                                    ->placeholder('')
                                    ->preload()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('new_contract_id')
                                    ->label('Contratto')
                                    ->relationship(name: 'contract', titleAttribute: 'office_name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                                    )
                                    ->searchable()
                                    ->placeholder('')
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->columnSpan(4),
                                Forms\Components\Select::make('tax_type')
                                    ->label('Tipo entrata')
                                    ->options(TaxType::class)
                                    ->searchable()
                                    ->placeholder('')
                                    ->preload()
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['client_id'] ?? null, fn ($q, $v) => $q->where('client_id', $v))
                            ->when($data['notify_type'] ?? null, fn ($q, $v) => $q->where('notify_type', $v))
                            ->when($data['new_contract_id'] ?? null, fn ($q, $v) => $q->where('new_contract_id', $v))
                            ->when($data['tax_type'] ?? null, fn ($q, $v) => $q->where('tax_type', $v));
                    }),
                // SEZIONE: Dati di Invio e Protocollo
                Tables\Filters\Filter::make('invio_protocollo')
                    ->form([
                        Forms\Components\Section::make('Dati di invio e protocollo')
                            ->collapsed()
                            ->columns(12)
                            ->schema([
                                Forms\Components\TextInput::make('send_protocol_number')
                                    ->label('Numero protocollo invio')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('send_protocol_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data protocollo invio da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('send_protocol_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data protocollo invio a')
                                    ->columnSpan(2),
                                Forms\Components\Select::make('shipment_type_id')
                                    ->label('Modalità di invio')
                                    ->relationship('shipmentType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('manage_year')
                                    ->label('Anno di gestione')
                                    ->numeric()
                                    ->rules(['digits:4'])
                                    ->columnSpan(2),
                                Forms\Components\Select::make('supplier_id')
                                    ->label('Fornitore')
                                    ->relationship('supplier', 'denomination')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('recipient')
                                    ->label('Destinatario notifica/trasgressore')
                                    ->maxLength(255)
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('supplier_name')
                                    ->label('Ente da rimborsare')
                                    ->maxLength(255)
                                    ->columnSpan(4),
                                Forms\Components\Select::make('act_type_id')
                                    ->label('Tipo atto')
                                    ->relationship('actType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('act_id')
                                    ->label('ID atto')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('act_year')
                                    ->label('Anno atto')
                                    ->numeric()
                                    ->rules(['digits:4'])
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('act_attachment_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data allegato atto da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('act_attachment_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data allegato atto a')
                                    ->columnSpan(2),
                                Forms\Components\Select::make('shipment_insert_user_id')
                                    ->label('Utente inserimento dati')
                                    ->relationship('shipmentInsertUser', 'name')
                                    ->searchable()
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('shipment_insert_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento dati')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('shipment_insert_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento dati')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['send_protocol_number'] ?? null, fn ($q, $v) => $q->where('send_protocol_number', 'like', "%{$v}%"))
                            ->when($data['send_protocol_date_from'] ?? null, fn ($q, $v) => $q->whereDate('send_protocol_date', '>=', $v))
                            ->when($data['send_protocol_date_to'] ?? null, fn ($q, $v) => $q->whereDate('send_protocol_date', '<', $v))
                            ->when($data['shipment_type_id'] ?? null, fn ($q, $v) => $q->where('shipment_type_id', $v))
                            ->when($data['recipient'] ?? null, fn ($q, $v) => $q->where('recipient', 'like', "%{$v}%"))
                            ->when($data['supplier_id'] ?? null, fn ($q, $v) => $q->where('supplier_id', $v))
                            ->when($data['supplier_name'] ?? null, fn ($q, $v) => $q->where('supplier_name', 'like', "%{$v}%"))
                            ->when($data['manage_year'] ?? null, fn ($q, $v) => $q->where('manage_year', $v))
                            ->when($data['act_type_id'] ?? null, fn ($q, $v) => $q->where('act_type_id', $v))
                            ->when($data['act_id'] ?? null, fn ($q, $v) => $q->where('act_id', 'like', "%{$v}%"))
                            ->when($data['act_year'] ?? null, fn ($q, $v) => $q->where('act_year', $v))
                            ->when($data['act_attachment_date_from'] ?? null, fn ($q, $v) => $q->whereDate('act_attachment_date', '>=', $v))
                            ->when($data['act_attachment_date_to'] ?? null, fn ($q, $v) => $q->whereDate('act_attachment_date', '<', $v))
                            ->when($data['shipment_insert_user_id'] ?? null, fn ($q, $v) => $q->where('shipment_insert_user_id', $v))
                            ->when($data['shipment_insert_date_from'] ?? null, fn ($q, $v) => $q->whereDate('shipment_insert_date', '>=', $v))
                            ->when($data['shipment_insert_date_to'] ?? null, fn ($q, $v) => $q->whereDate('shipment_insert_date', '<', $v));
                    }),
                // SEZIONE: Lavorazione e Notifica
                Tables\Filters\Filter::make('notifica')
                    ->form([
                        Forms\Components\Section::make('Lavorazione e Notifica')
                            ->collapsed()
                            ->columns(12)
                            ->schema([
                                Forms\Components\TextInput::make('order_rif')
                                    ->label('Riferimento')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                // Forms\Components\TextInput::make('list_rif')
                                //     ->label('Riferimento distinta')
                                //     ->maxLength(255)
                                //     ->columnSpan(3),
                                Forms\Components\TextInput::make('receive_protocol_number')
                                    ->label('Numero prot. ricezione')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('receive_protocol_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data prot. ricezione da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('receive_protocol_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data prot. ricezione a')
                                    ->columnSpan(2),
                                Forms\Components\Select::make('notify_month')
                                    ->label('Mese ricezione')
                                    ->options(Month::class)
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('notify_year')
                                    ->label('Anno ricezione')
                                    ->numeric()
                                    ->rules(['digits:4'])
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('notify_amount')
                                    ->label('Importo notifica')
                                    ->numeric()
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    // ->afterStateUpdated(function ($state, $component) {
                                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                    //     }
                                    //     else {
                                    //         $amount = $state ?? 0;
                                    //     }
                                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                    //     $number = str_replace(',', '.', $clean);
                                    //     $float = floatval($number);
                                    //     $formatted = number_format($float, 2, ',', '.');
                                    //     $component->state($formatted);
                                    // })
                                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                                    ->afterStateUpdated(function ($state, $component) {
                                        $float = CurrencyService::parseNumber($state);
                                        $formatted = number_format($float, 2, ',', '.');
                                        $component->state($formatted);
                                    })
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                                    ->suffix('€')
                                    ->visible(false)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('amount_registration_date')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data registrazione importo')
                                    ->visible(false)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('notify_attachment_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data allegato notifica da')
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('notify_attachment_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data allegato notifica a')
                                    ->columnSpan(3),
                                Forms\Components\Select::make('notify_insert_user_id')
                                    ->label('Utente inserimento notifica')
                                    ->relationship('notifyInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('notify_insert_date')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento notifica')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('notify_insert_date')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento notifica')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['order_rif'] ?? null, fn ($q, $v) => $q->where('order_rif', 'like', "%{$v}%"))
                            ->when($data['list_rif'] ?? null, fn ($q, $v) => $q->where('list_rif', 'like', "%{$v}%"))
                            ->when($data['receive_protocol_number'] ?? null, fn ($q, $v) => $q->where('receive_protocol_number', 'like', "%{$v}%"))
                            ->when($data['receive_protocol_date_from'] ?? null, fn ($q, $v) => $q->whereDate('receive_protocol_date', '>=', $v))
                            ->when($data['receive_protocol_date_to'] ?? null, fn ($q, $v) => $q->whereDate('receive_protocol_date', '<', $v))
                            ->when($data['notify_year'] ?? null, fn ($q, $v) => $q->where('notify_year', $v))
                            ->when($data['notify_month'] ?? null, fn ($q, $v) => $q->where('notify_month', $v))
                            ->when($data['notify_amount'] ?? null, fn ($q, $v) => $q->where('notify_amount', $v))
                            ->when($data['amount_registration_date'] ?? null, fn ($q, $v) => $q->whereDate('amount_registration_date', $v))
                            ->when($data['notify_attachment_date_from'] ?? null, fn ($q, $v) => $q->whereDate('notify_attachment_date', '>=', $v))
                            ->when($data['notify_attachment_date_to'] ?? null, fn ($q, $v) => $q->whereDate('notify_attachment_date', '<', $v))
                            ->when($data['notify_insert_user_id'] ?? null, fn ($q, $v) => $q->where('notify_insert_user_id', $v))
                            ->when($data['notify_insert_date_from'] ?? null, fn ($q, $v) => $q->whereDate('notify_insert_date', '>=', $v))
                            ->when($data['notify_insert_date_to'] ?? null, fn ($q, $v) => $q->whereDate('notify_insert_date', '<', $v));
                    }),
                // SEZIONE: Gestione Spese
                Tables\Filters\Filter::make('spese')
                    ->form([
                        Forms\Components\Section::make('Gestione Spese')
                            ->collapsed()
                            ->columns(12)
                            ->schema([
                                // Forms\Components\Toggle::make('reinvoice')
                                //     ->label('Rifatturazione spese')
                                //     ->columnSpan(4),
                                Forms\Components\Select::make('reinvoice_type')
                                    ->label('Tipo rifatturazione')
                                    ->options(ReinvoiceType::class)
                                    ->preload()
                                    ->columnSpan(4),
                                Forms\Components\Select::make('passive_invoice_id')
                                    ->label('Fattura passiva')
                                    ->options(function (Get $get): array {
                                        $supplierId = $get('supplier_id');
                                        if (!$supplierId) {
                                            return [];
                                        }
                                        return PassiveInvoice::where('supplier_id', $get('supplier_id'))
                                            ->pluck('description', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(8),
                                Forms\Components\TextInput::make('notify_expense_amount')
                                    ->label('Importo spese notifica')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    // ->afterStateUpdated(function ($state, $component) {
                                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                    //     }
                                    //     else {
                                    //         $amount = $state ?? 0;
                                    //     }
                                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                    //     $number = str_replace(',', '.', $clean);
                                    //     $float = floatval($number);
                                    //     $formatted = number_format($float, 2, ',', '.');
                                    //     $component->state($formatted);
                                    // })
                                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                                    ->afterStateUpdated(function ($state, $component) {
                                        $float = CurrencyService::parseNumber($state);
                                        $formatted = number_format($float, 2, ',', '.');
                                        $component->state($formatted);
                                    })
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                                    ->suffix('€')
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('mark_expense_amount')
                                    ->label('Importo spese contrassegno')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    // ->afterStateUpdated(function ($state, $component) {
                                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                    //     }
                                    //     else {
                                    //         $amount = $state ?? 0;
                                    //     }
                                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                    //     $number = str_replace(',', '.', $clean);
                                    //     $float = floatval($number);
                                    //     $formatted = number_format($float, 2, ',', '.');
                                    //     $component->state($formatted);
                                    // })
                                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                                    ->afterStateUpdated(function ($state, $component) {
                                        $float = CurrencyService::parseNumber($state);
                                        $formatted = number_format($float, 2, ',', '.');
                                        $component->state($formatted);
                                    })
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                                    ->suffix('€')
                                    ->columnSpan(4),

                                Forms\Components\Select::make('shipment_doc_type')
                                    ->label('Tipo documento spedizione')
                                    ->options(ShipmentDocType::class)
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('shipment_doc_number')
                                    ->label('Numero documento')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('shipment_doc_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data documento da')
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('shipment_doc_date to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data documento a')
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\Select::make('expense_insert_user_id')
                                    ->label('Utente inserimento spese')
                                    ->relationship('expenseInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('expense_insert_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento spese da')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('expense_insert_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento spese a')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['passive_invoice_id'] ?? null, fn ($q, $v) => $q->where('passive_invoice_id', $v))
                            ->when($data['notify_expense_amount'] ?? null, fn ($q, $v) => $q->where('notify_expense_amount', $v))
                            ->when($data['mark_expense_amount'] ?? null, fn ($q, $v) => $q->where('mark_expense_amount', $v))
                            ->when($data['reinvoice_type'] ?? null, fn ($q, $v) => $q->where('reinvoice_type', $v))
                            ->when($data['shipment_doc_type'] ?? null, fn ($q, $v) => $q->where('shipment_doc_type', $v))
                            ->when($data['shipment_doc_number'] ?? null, fn ($q, $v) => $q->where('shipment_doc_number', 'like', "%{$v}%"))
                            ->when($data['shipment_doc_date_from'] ?? null, fn ($q, $v) => $q->whereDate('shipment_doc_date', '>=', $v))
                            ->when($data['shipment_doc_date_to'] ?? null, fn ($q, $v) => $q->whereDate('shipment_doc_date', '<', $v))
                            ->when($data['iban'] ?? null, fn ($q, $v) => $q->where('iban', 'like', "%{$v}%"))
                            ->when($data['expense_insert_user_id'] ?? null, fn ($q, $v) => $q->where('expense_insert_user_id', $v))
                            ->when($data['expense_insert_date_from'] ?? null, fn ($q, $v) => $q->whereDate('expense_insert_date', '>=', $v))
                            ->when($data['expense_insert_date_to'] ?? null, fn ($q, $v) => $q->whereDate('expense_insert_date', '<', $v));
                    }),
                // SEZIONE: Pagamenti
                Tables\Filters\Filter::make('pagamenti')
                    ->form([
                        Forms\Components\Section::make('Pagamenti')
                            ->collapsed()
                            ->columns(12)
                            ->schema([
                                Forms\Components\Toggle::make('payed')
                                    ->label('Spese pagate')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('payment_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data pagamento da')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('payment_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data pagamento a')
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('payment_total')
                                    ->label('Totale pagamenti')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    // ->afterStateUpdated(function ($state, $component) {
                                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                    //     }
                                    //     else {
                                    //         $amount = $state ?? 0;
                                    //     }
                                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                    //     $number = str_replace(',', '.', $clean);
                                    //     $float = floatval($number);
                                    //     $formatted = number_format($float, 2, ',', '.');
                                    //     $component->state($formatted);
                                    // })
                                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                                    ->afterStateUpdated(function ($state, $component) {
                                        $float = CurrencyService::parseNumber($state);
                                        $formatted = number_format($float, 2, ',', '.');
                                        $component->state($formatted);
                                    })
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                                    ->suffix('€')
                                    ->columnSpan(4),
                                Forms\Components\Select::make('payment_insert_user_id')
                                    ->label('Utente inserimento pagamento')
                                    ->relationship('paymentInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('payment_insert_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento pagamento da')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('payment_insert_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento pagamento a')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['payed'] ?? null, fn ($q, $v) => $q->where('payed', $v))
                            ->when($data['payment_date_from'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
                            ->when($data['payment_date_to'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '<', $v))
                            ->when($data['payment_total'] ?? null, fn ($q, $v) => $q->where('payment_total', $v))
                            ->when($data['payment_insert_user_id'] ?? null, fn ($q, $v) => $q->where('payment_insert_user_id', $v))
                            ->when($data['payment_insert_date_from'] ?? null, fn ($q, $v) => $q->whereDate('payment_insert_date', '>=', $v))
                            ->when($data['payment_insert_date_to'] ?? null, fn ($q, $v) => $q->whereDate('payment_insert_date', '<', $v));
                    }),
                // SEZIONE: Rifatturazione
                Tables\Filters\Filter::make('rifatturazione')
                    ->form([
                        Forms\Components\Section::make('Rifatturazione')
                            ->collapsed()
                            ->columns(12)
                            ->schema([
                                Forms\Components\Select::make('reinvoice_id')
                                    ->label('Fattura emessa per rifatturazione')
                                    ->options(function (Get $get): array {
                                        return Invoice::where('client_id', $get('client_id'))
                                            ->whereNotNull('flow')
                                            ->pluck('description', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(6),
                                Forms\Components\TextInput::make('reinvoice_number')
                                    ->label('Numero fattura emessa')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('reinvoice_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data fattura emessa da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('reinvoice_date_tm')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data fattura emessa a')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('reinvoice_amount')
                                    ->label('Importo fattura emessa')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    // ->afterStateUpdated(function ($state, $component) {
                                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                    //     }
                                    //     else {
                                    //         $amount = $state ?? 0;
                                    //     }
                                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                    //     $number = str_replace(',', '.', $clean);
                                    //     $float = floatval($number);
                                    //     $formatted = number_format($float, 2, ',', '.');
                                    //     $component->state($formatted);
                                    // })
                                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                                    ->afterStateUpdated(function ($state, $component) {
                                        $float = CurrencyService::parseNumber($state);
                                        $formatted = number_format($float, 2, ',', '.');
                                        $component->state($formatted);
                                    })
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                                    ->suffix('€')
                                    ->columnSpan(4),
                                Forms\Components\Select::make('reinvoice_insert_user_id')
                                    ->label('Utente inserimento rifatturazione')
                                    ->relationship('reinvoiceInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('reinvoice_insert_date_from')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento rifatturazione da')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('reinvoice_insert_date_to')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->label('Data inserimento rifatturazione a')
                                    // ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->visible(fn (): bool => Auth::user()->isSuperAdmin())
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['reinvoice_id'] ?? null, fn ($q, $v) => $q->where('reinvoice_id', $v))
                            ->when($data['reinvoice_number'] ?? null, fn ($q, $v) => $q->where('reinvoice_number', 'like', "%{$v}%"))
                            ->when($data['reinvoice_date_from'] ?? null, fn ($q, $v) => $q->whereDate('reinvoice_date', '>=', $v))
                            ->when($data['reinvoice_date_to'] ?? null, fn ($q, $v) => $q->whereDate('reinvoice_date', '<', $v))
                            ->when($data['reinvoice_amount'] ?? null, fn ($q, $v) => $q->where('reinvoice_amount', $v))
                            ->when($data['reinvoice_insert_user_id'] ?? null, fn ($q, $v) => $q->where('reinvoice_insert_user_id', $v))
                            ->when($data['reinvoice_insert_date_from'] ?? null, fn ($q, $v) => $q->whereDate('reinvoice_insert_date', '>=', $v))
                            ->when($data['reinvoice_insert_date_to'] ?? null, fn ($q, $v) => $q->whereDate('reinvoice_insert_date', '<', $v));
                    }),
                // SEZIONE: Registrazione e Allegati
                // Tables\Filters\Filter::make('registrazione_allegati')
                //     ->form([
                //         Forms\Components\Section::make('Registrazione e Allegati')
                //             ->collapsed()
                //             ->visible(false)
                //             ->columns(12)
                //             ->schema([
                //                 Forms\Components\DatePicker::make('notify_date_registration_date')
                //                     ->label('Data registrazione data di notifica')
                //                     ->columnSpan(4),
                //                 Forms\Components\DatePicker::make('reinvoice_attachment_date')
                //                     ->label('Data file fattura emessa caricato')
                //                     ->columnSpan(4),
                //                 Forms\Components\Select::make('reinvoice_registration_user_id')
                //                     ->label('Utente registrazione')
                //                     ->relationship('reinvoiceRegistrationUser', 'name')
                //                     ->searchable()
                //                     ->preload()
                //                     ->optionsLimit(5)
                //                     ->columnSpan(4),
                //                 Forms\Components\DatePicker::make('reinvoice_registration_date')
                //                     ->label('Data registrazione')
                //                     ->columnSpan(4),
                //             ]),
                //     ])
                //     ->query(function (Builder $query, array $data): Builder {
                //         return $query
                //             ->when($data['notify_date_registration_date'] ?? null, fn ($q, $v) => $q->whereDate('notify_date_registration_date', $v))
                //             ->when($data['reinvoice_attachment_date'] ?? null, fn ($q, $v) => $q->whereDate('reinvoice_attachment_date', $v))
                //             ->when($data['reinvoice_registration_user_id'] ?? null, fn ($q, $v) => $q->where('reinvoice_registration_user_id', $v))
                //             ->when($data['reinvoice_registration_date'] ?? null, fn ($q, $v) => $q->whereDate('reinvoice_registration_date', $v));
                //     }),
                // SEZIONE: Note
                Tables\Filters\Filter::make('note')
                    ->form([
                        // Forms\Components\Section::make('Note')
                        //     ->collapsed()
                        //     ->schema([
                                Forms\Components\TextInput::make('note')
                                    ->label('Note')
                                    ->columnSpanFull(),
                            // ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['note'] ?? null, fn ($q, $v) => $q->where('note', 'like', "%{$v}%"));
                    }),
            // ], layout: FiltersLayout::Modal)->filtersFormColumns(1)
            ])->filtersFormColumns(1)
            ->persistFiltersInSession()
            ->filtersFormWidth(MaxWidth::SevenExtraLarge)
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make()
                //     ->modalWidth(MaxWidth::SevenExtraLarge)
                //     ->extraAttributes([
                //         'style' => 'max-width: min(95vw, 1600px) !important;'
                //     ]),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_act_attachment')
                        ->label('Allegato Atto')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => 
                            !empty($record->act_attachment_path) && Storage::exists($record->act_attachment_path)
                                ? Storage::temporaryUrl($record->act_attachment_path, now()->addMinutes(1)) 
                                : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => 
                            !empty($record->act_attachment_path) && 
                            Storage::exists($record->act_attachment_path)
                        ),
                    Tables\Actions\Action::make('view_notify_attachment')
                        ->label('Allegato Notifica')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => 
                            !empty($record->notify_attachment_path) && Storage::exists($record->notify_attachment_path)
                                ? Storage::temporaryUrl($record->notify_attachment_path, now()->addMinutes(1)) 
                                : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => 
                            !empty($record->notify_attachment_path) && 
                            Storage::exists($record->notify_attachment_path)
                        ),
                    Tables\Actions\Action::make('view_reinvoice_attachment')
                        ->label('Allegato Rifatturazione')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => 
                            !empty($record->reinvoice_attachment_path) && Storage::exists($record->reinvoice_attachment_path)
                                ? Storage::temporaryUrl($record->reinvoice_attachment_path, now()->addMinutes(1)) 
                                : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => 
                            !empty($record->reinvoice_attachment_path) && 
                            Storage::exists($record->reinvoice_attachment_path)
                        ),
                ])
                ->label('Allegati')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->button(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostalExpenses::route('/'),
            'create' => Pages\CreatePostalExpense::route('/create'),
            'edit' => Pages\EditPostalExpense::route('/{record}/edit'),
            'view' => Pages\ViewPostalExpense::route('/{record}'),
        ];
    }

    public static function modalForm(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                //
            ]);
    }
}
