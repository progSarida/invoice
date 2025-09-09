<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\PostalExpenseResource\Pages;
use App\Filament\Company\Resources\PostalExpenseResource\RelationManagers;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\ExpenseType;
use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\ShipmentDocType;
use App\Enums\TaxType;
use App\Models\ActType;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\NewContract;
use App\Models\PassiveInvoice;
use App\Models\PostalExpense;
use App\Models\ShipmentType;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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
        return $form
            ->schema([
                // SEZIONE: Informazioni Base e Identificazione
                Forms\Components\Section::make('Informazioni di base per l\'identificazione della spesa postale')
                    ->icon('heroicon-o-identification')
                    ->collapsed(false)
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('client_id')->label('Cliente')
                            ->relationship(name: 'client', titleAttribute: 'denomination')
                            ->getOptionLabelFromRecordUsing(
                                fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                            )
                            ->required()
                            ->searchable('denomination')
                            ->live()
                            ->placeholder('Seleziona')
                            ->preload()
                            ->optionsLimit(5)
                            ->columnSpan(12)
                            ->autofocus(fn($record): bool => !$record),

                        Forms\Components\Select::make('notify_type')->label('Tipo notifica')
                            ->required()
                            ->options(NotifyType::class)
                            ->searchable()
                            ->live()
                            ->placeholder('Seleziona')
                            ->preload()
                            ->columnSpan(3),

                        Forms\Components\Select::make('new_contract_id')->label('Contratto')
                            ->relationship(
                                name: 'contract',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$get('client_id'))
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                            )
                            ->afterStateUpdated(function (Set $set, $state) {
                                $contract = NewContract::find($state);
                                if ($contract) {
                                    // $set('tax_type', $contract->tax_type);
                                    $set('reinvoice', $contract->reinvoice);
                                }
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->preload()
                            ->optionsLimit(5)
                            ->columnSpan(6),

                        Forms\Components\Select::make('tax_type')->label('Tipo entrata')
                            ->required()
                            ->options(TaxType::class)
                            ->searchable()
                            ->live()
                            ->placeholder('Seleziona')
                            ->preload()
                            ->columnSpan(3),
                    ]),

                // SEZIONE: Dati di Invio e Protocollo
                Forms\Components\Section::make('Dati relativi al protocollo di invio e alla classificazione dell\'atto inviato in lavorazione/notifica')
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsed(fn($record): bool => $record && $record->shipmentInserted())
                    ->schema([
                        Forms\Components\TextInput::make('send_protocol_number')->label('Numero protocollo invio')
                            ->required()
                            ->maxLength(255)
                            ->default(function () {
                                $maxProtocolNumber = \App\Models\PostalExpense::query()
                                    ->selectRaw('MAX(CAST(send_protocol_number AS UNSIGNED)) as max_number')
                                    ->value('max_number');

                                return $maxProtocolNumber ? $maxProtocolNumber + 1 : 1;
                            }),

                        Forms\Components\DatePicker::make('send_protocol_date')->label('Data protocollo invio')
                            ->required()
                            ->default(now()->toDateString()),

                        Forms\Components\Select::make('shipment_type_id')->label('Modalità di invio')
                            ->required()
                            ->relationship('shipmentType', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('recipient')->label('Destinatario notifica/trasgressore')
                            ->maxLength(255)
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            ->columnSpanFull(),

                        // Fornitore/Supplier condizionale
                        Forms\Components\Select::make('supplier_id')->label('Fornitore')
                            ->relationship('supplier', 'denomination')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('passive_invoice_id', null);
                            })
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('supplier_name')->label('Ente da rimborsare')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            ->columnSpanFull(),

                        // Gestione anni
                        Forms\Components\TextInput::make('manage_year')->label('Anno di gestione')
                            ->required()
                            ->numeric()
                            ->rules(['digits:4'])
                            ->default(now()->year),

                        Forms\Components\Select::make('act_type_id')->label('Tipo atto')
                            ->required()
                            ->relationship('actType', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('act_id')->label('ID atto')
                            ->maxLength(255)
                            ->visible(false),

                        Forms\Components\TextInput::make('act_year')->label('Anno atto')
                            ->numeric()
                            ->rules(['digits:4'])
                            ->default(now()->year)
                            ->visible(false),

                        Forms\Components\FileUpload::make('act_attachment_path')->label('Allegato atto')
                            ->required()
                            ->disk('public')
                            ->directory('reg_richiesta')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (!empty($state)) {
                                    $set('act_attachment_date', now()->toDateString());
                                } else {
                                    $set('act_attachment_date', null);
                                }
                            })
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file,Get $get, $record) {
                                // Genera un nome personalizzato per il file
                                $number = $get('send_protocol_number') ?? '******';                                 // numero protocollo invio
                                $date = $get('send_protocol_date') ?? '******';                                     // data protocollo invio
                                $shipmentType = ShipmentType::find($get('shipment_type_id'))->name ?? 'modalita';   // modalità invio
                                $client = Client::find($get('client_id'))->denomination;                            // cliente
                                $taxType = $get('tax_type')->getLabel();                                            // entrata
                                $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                      // tipo atto
                                $extension = $file->getClientOriginalExtension();                                   // estensione

                                return sprintf('%s_%s_REG-RIGHIESTA_%s_%s_%s_%s.%s', $number, $date, $shipmentType, $client, $taxType, $actType, $extension);
                            })
                            ->maxSize(10240),

                        Forms\Components\DatePicker::make('act_attachment_date')->label('Data allegato atto')
                            // ->required()
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value),
                            // ->visible(function (Get $get, $record): bool {
                            //     $hasUploadedFile = !empty($get('act_attachment_path'));
                            //     $hasSavedFile = $record && !empty($record->act_attachment_path);
                            //     return $hasUploadedFile || $hasSavedFile;
                            // }),

                        Forms\Components\Select::make('shipment_insert_user_id')->label('Utente inserimento dati')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->shipment_insert_user_id)
                            ->relationship('shipmentInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('shipment_insert_date')->label('Data inserimento dati')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->shipment_insert_date),
                    ])
                    ->columns(3),

                // SEZIONE: Lavorazione e Notifica
                Forms\Components\Section::make('Dati relativi alla lavorazione/notifica richiesta ed effettuata dal fornitore incaricato')
                    ->icon('heroicon-o-bell-alert')
                    ->collapsed(fn($record): bool => $record && $record->notificationInserted())
                    ->visible(fn($record): bool => $record && ($record->shipment_insert_user_id && $record->shipment_insert_date))
                    ->schema([
                        Forms\Components\TextInput::make('order_rif')->label('Riferimento commessa')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('list_rif')->label('Riferimento distinta')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('receive_protocol_number')->label('Numero protocollo ricezione')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value),

                        Forms\Components\DatePicker::make('receive_protocol_date')->label('Data protocollo ricezione')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $date = \Carbon\Carbon::parse($state);
                                    $set('notify_year', $date->year);
                                    $set('notify_month', $date->month);
                                }
                            }),

                        Forms\Components\TextInput::make('notify_year')->label('Anno ricezione')
                            ->numeric()
                            ->rules(['digits:4'])
                            ->default(now()->year),

                        Forms\Components\Select::make('notify_month')->label('Mese ricezione')
                            ->options(Month::class)
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('notify_amount')->label('Importo notifica')
                            ->required()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $set('amount_registration_date', now()->toDateString());
                                }
                            }),

                        Forms\Components\DatePicker::make('amount_registration_date')->label('Data registrazione importo')
                            ->required(),

                        Forms\Components\FileUpload::make('notify_attachment_path')->label('Allegato notifica')
                            ->required()
                            ->autofocus(fn($record): bool => $record && $record->shipmentInserted())
                            ->disk('public')
                            ->directory('reg_post_richiesta')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])->afterStateUpdated(function (Set $set, $state) {
                                if (!empty($state)) {
                                    $set('notify_attachment_date', now()->toDateString());
                                } else {
                                    $set('notify_attachment_date', null);
                                }
                            })
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file,Get $get, $record) {
                                // Genera un nome personalizzato per il file
                                $date = $get('receive_protocol_date') ?? '******';                                                      // data protocollo ricezione
                                $shipmentType = ShipmentType::find($get('shipment_type_id'))->name ?? 'modalita';                       // modalità invio
                                $client = Client::find($get('client_id'))->denomination;                                                // cliente
                                $taxType = TaxType::from($get('tax_type'))->getLabel();                                                 // entrata
                                $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                                          // tipo atto
                                $rifOrder = $get('order_rif');                                                                          // rif2 (commessa)
                                $rifList = $get('list_rif');                                                                            // rif2 (distinta)
                                $amount = ($record->notify_amount ?? 0);                                                                // importo
                                $extension = $file->getClientOriginalExtension();                                                       // estensione

                                return sprintf('%s_REG-POST-RIGHIESTA_%s_%s_%s_%s_%s_%s_%s.%s', $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $rifList, $amount, $extension);
                            })
                            ->maxSize(10240),

                        Forms\Components\DatePicker::make('notify_attachment_date')->label('Data allegato notifica')
                            // ->required()
                            // ->visible(function (Get $get, $record): bool {
                            //     $hasUploadedFile = !empty($get('notify_attachment_path'));
                            //     $hasSavedFile = $record && !empty($record->notify_attachment_path);
                            //     return $hasUploadedFile || $hasSavedFile;
                            // })
                            ,

                        Forms\Components\Select::make('notify_insert_user_id')->label('Utente inserimento notifica')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->notify_insert_user_id)
                            ->relationship('notifyInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('notify_insert_date')->label('Data inserimento notifica')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->notify_insert_date),
                    ])
                    ->columns(3),

                // SEZIONE: Gestione Spese
                Forms\Components\Section::make('Riferimenti alle spese della lavorazione/notifica richiesta')
                    ->icon('heroicon-o-currency-euro')
                    ->collapsed(fn($record): bool => $record && $record->expenseInserted())
                    ->visible(fn($record): bool => $record && ($record->notify_insert_user_id && $record->notify_insert_date))
                    ->schema([
                        // Forms\Components\Select::make('expense_type')->label('Tipologia spesa')
                        //     ->required()
                        //     ->autofocus(fn($record): bool => $record && $record->notificationInserted())
                        //     ->options(ExpenseType::class)
                        //     ->searchable()
                        //     ->live()
                        //     ->preload()
                        //     ->columnSpanFull(),

                        Forms\Components\Select::make('passive_invoice_id')->label('Fattura passiva')
                            ->required()
                            // ->relationship('passiveInvoice', 'description')
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                            ->options(function (Get $get): array {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) { return []; }
                                return PassiveInvoice::where('supplier_id', $supplierId)
                                    ->pluck('description', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $passiveInvoice = PassiveInvoice::find($state);
                                    $set('notify_expense_amount', $passiveInvoice->total);
                                    $set('shipment_doc_number', $passiveInvoice->number);
                                    $set('shipment_doc_date', $passiveInvoice->invoice_date->toDateString());
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('notify_expense_amount')->label('Importo spese notifica')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\TextInput::make('mark_expense_amount')->label('Importo spese contrassegno')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            ->suffix('€'),

                        Forms\Components\Toggle::make('reinvoice')->label('Rifatturazione spese')
                            ->disabled(),

                        Forms\Components\Select::make('shipment_doc_type')->label('Tipo documento spedizione')
                            ->required()
                            ->options(ShipmentDocType::class)
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('shipment_doc_number')->label('Numero documento')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('shipment_doc_date')->label('Data documento')
                            ->required(),

                        Forms\Components\TextInput::make('iban')->label('IBAN')
                            ->maxLength(255)
                            ->rules(['iban']),

                        Forms\Components\Select::make('expense_insert_user_id')->label('Utente inserimento spese')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->expense_insert_user_id)
                            ->relationship('expenseInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('expense_insert_date')->label('Data inserimento spese')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->expense_insert_date),
                    ])
                    ->columns(3),

                // SEZIONE: Pagamenti
                Forms\Components\Section::make('Informazioni relative ai pagamenti delle spese')
                    ->icon('heroicon-o-credit-card')
                    ->collapsed(fn($record): bool => $record && $record->paymentInserted())
                    ->visible(fn($record): bool => $record && ($record->expense_insert_user_id && $record->expense_insert_date))
                    ->schema([
                        Forms\Components\Toggle::make('payed')->label('Spese pagate')
                            ->autofocus(fn($record): bool => $record && $record->expenseInserted())
                            ->live(),

                        Forms\Components\DatePicker::make('payment_date')->label('Data pagamento')
                            ->required()
                            ->helperText('In caso di più pagamenti, inserire la data dell\'ultimo pagamento'),

                        Forms\Components\TextInput::make('payment_total')->label('Totale pagamenti')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\Select::make('payment_insert_user_id')->label('Utente inserimento pagamento')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->payment_insert_user_id)
                            ->relationship('paymentInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('payment_insert_date')->label('Data inserimento pagamento')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->payment_insert_date),
                    ])
                    ->columns(3),

                // SEZIONE: Rifatturazione
                Forms\Components\Section::make('Estremi della rifatturazione delle spese della lavorazione/notifica')
                    ->icon('heroicon-o-receipt-refund')
                    ->collapsed(fn($record): bool => $record && $record->reinvoiceInserted())
                    ->visible(fn($record): bool => $record && $record->reinvoice && ($record->payment_insert_user_id && $record->payment_insert_date))
                    ->schema([
                        Forms\Components\Select::make('reinvoice_id')->label('Fattura emessa per rifatturazione')
                            ->required()
                            // ->relationship('reInvoice', 'description')
                            ->options(function (Get $get): array {
                                return Invoice::where('client_id', $get('client_id'))
                                    ->whereNotNull('flow')
                                    ->pluck('description', 'id')
                                    ->toArray();
                            })
                            ->autofocus(fn($record): bool => $record && !$record->paymentInserted())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if($state){
                                    $invoice = Invoice::find($state);
                                    if ($invoice) {
                                        $set('reinvoice_number', $invoice->number);
                                        $set('reinvoice_date', $invoice->invoice_date->format('Y-m-d'));
                                        $set('reinvoice_amount', $invoice->total);
                                    }
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('reinvoice_number')->label('Numero fattura emessa')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('reinvoice_date')->label('Data fattura emessa')
                            ->required(),

                        Forms\Components\TextInput::make('reinvoice_amount')->label('Importo fattura emessa')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\Select::make('reinvoice_insert_user_id')->label('Utente inserimento rifatturazione')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->reinvoice_insert_user_id)
                            ->relationship('reinvoiceInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('reinvoice_insert_date')->label('Data inserimento rifatturazione')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->reinvoice_insert_date),
                    ])
                    ->columns(3),

                // SEZIONE: Registrazione e Allegati
                Forms\Components\Section::make('Registrazione della data di lavorazione/modifica e allegati')
                    ->icon('heroicon-o-document-text')
                    ->collapsed(false)
                    // ->collapsed(fn($record): bool => $record && $record->reinvoiceRegistered())
                    // ->visible(fn($record): bool => $record && ($record->reinvoice_insert_user_id && $record->reinvoice_insert_date))
                    ->visible(function ($record) {
                        // è un invio tramite messo
                        $isMessenger = $record && $record->notify_type === NotifyType::MESSO;
                        // è una spedizione di una raccomandata con ricevuta di ritorno o di un atto giudiziario
                        $hasReceipt = $record && $record->notify_type === NotifyType::SPEDIZIONE && in_array(
                            ShipmentType::find($record->shipment_type_id)?->name,
                            ['Raccomandata AR' , 'Atto giudiziario']
                        );
                        // le sezioni precedenti sono state inserite
                        $isStep = $record && ($record->reinvoice_insert_user_id && $record->reinvoice_insert_date);
                        return ($isMessenger || $hasReceipt) && $isStep;
                    })
                    ->schema([
                        Forms\Components\DatePicker::make('notify_date_registration_date')->label('Data registrazione data di notifica')
                            ->required(),

                        Forms\Components\FileUpload::make('reinvoice_attachment_path')->label('Allegato fattura emessa')
                            ->required()
                            ->disk('public')
                            ->directory('reg_not_db')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (!empty($state)) {
                                    $set('reinvoice_attachment_date', now()->toDateString());
                                } else {
                                    $set('reinvoice_attachment_date', null);
                                }
                            })
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file,Get $get, $record) {
                                // Genera un nome personalizzato per il file
                                $date = $get('notify_date_registration_date') ?? '******';                                              // data registrazione data notifica
                                $client = Client::find($get('client_id'))->denomination;                                                // cliente
                                $taxType = TaxType::from($get('tax_type'))->getLabel();                                                 // entrata
                                $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                                          // tipo atto
                                $extension = $file->getClientOriginalExtension();                                                       // estensione

                                return sprintf('%s_REG-POST-RIGHIESTA_%s_%s_%s.%s', $date, $client, $taxType, $actType, $extension);
                            })
                            ->autofocus(fn($record): bool => $record && $record->reinvoiceInserted()),

                        Forms\Components\DatePicker::make('reinvoice_attachment_date')->label('Data file fattura emessa caricato')
                            // ->required()
                            ,

                        Forms\Components\Select::make('reinvoice_registration_user_id')->label('Utente registrazione')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->reinvoice_registration_user_id)
                            ->relationship('reinvoiceRegistrationUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('reinvoice_registration_date')->label('Data registrazione')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->reinvoice_registration_date),
                    ])
                    ->columns(3),

                // SEZIONE: Note
                Forms\Components\Section::make('Note')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->collapsed(false)
                    ->visible()
                    ->schema([
                        Forms\Components\Textarea::make('note')->label('Note')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // SEZIONE: Visualizzazione Allegati (nascosta se non ci sono allegati)
                Forms\Components\Section::make('Visualizza Allegati')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsed()
                    ->visible(fn($record): bool => $record && ($record->act_attachment_path || $record->notify_attachment_path || $record->reinvoice_attachment_path))
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('view_act_attachment')
                                ->label('Visualizza Allegato Atto')
                                ->icon('heroicon-o-eye')
                                ->url(fn($record): ?string => $record && $record->act_attachment_path ? Storage::url($record->act_attachment_path) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->act_attachment_path)
                                ->color('primary'),

                            Forms\Components\Actions\Action::make('view_notify_attachment')
                                ->label('Visualizza Allegato Notifica')
                                ->icon('heroicon-o-eye')
                                ->url(fn($record): ?string => $record && $record->notify_attachment_path ? Storage::url($record->notify_attachment_path) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->notify_attachment_path)
                                ->color('primary'),

                            Forms\Components\Actions\Action::make('view_reinvoice_attachment')
                                ->label('Visualizza Allegato Rifatturazione')
                                ->icon('heroicon-o-eye')
                                ->url(fn($record): ?string => $record && $record->reinvoice_attachment_path ? Storage::url($record->reinvoice_attachment_path) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->reinvoice_attachment_path)
                                ->color('primary'),
                        ])->columnSpanFull()
                    ]),
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
                    ->label('Anno')
                    ->searchable(),
                Tables\Columns\TextColumn::make('actType.name')
                    // ->badge()
                    ->label('Tipo atto'),
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
                            ($record->notify_expense_amount ?? 0) +
                            ($record->mark_expense_amount ?? 0);
                        return $sum;
                    })
                    ->money('EUR'),
                Tables\Columns\IconColumn::make('reinvoice')
                    ->label('Rifatturare')
                    ->boolean(),
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
                                    ->relationship(name: 'client', titleAttribute: 'denomination')
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
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
                                    ->label('Data protocollo invio da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('send_protocol_date_to')
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
                                    ->label('Data allegato atto da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('act_attachment_date_to')
                                    ->label('Data allegato atto a')
                                    ->columnSpan(2),
                                Forms\Components\Select::make('shipment_insert_user_id')
                                    ->label('Utente inserimento dati')
                                    ->relationship('shipmentInsertUser', 'name')
                                    ->searchable()
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('shipment_insert_date_from')
                                    ->label('Data inserimento dati')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('shipment_insert_date_to')
                                    ->label('Data inserimento dati')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
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
                                    ->label('Riferimento commessa')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('list_rif')
                                    ->label('Riferimento distinta')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('receive_protocol_number')
                                    ->label('Numero prot. ricezione')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('receive_protocol_date_from')
                                    ->label('Data prot. ricezione da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('receive_protocol_date_to')
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
                                    ->suffix('€')
                                    ->visible(false)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('amount_registration_date')
                                    ->label('Data registrazione importo')
                                    ->visible(false)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('notify_attachment_date_from')
                                    ->label('Data allegato notifica da')
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('notify_attachment_date_to')
                                    ->label('Data allegato notifica a')
                                    ->columnSpan(3),
                                Forms\Components\Select::make('notify_insert_user_id')
                                    ->label('Utente inserimento notifica')
                                    ->relationship('notifyInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('notify_insert_date')
                                    ->label('Data inserimento notifica')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('notify_insert_date')
                                    ->label('Data inserimento notifica')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
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
                                Forms\Components\Toggle::make('reinvoice')
                                    ->label('Rifatturazione spese')
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
                                    ->suffix('€')
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('mark_expense_amount')
                                    ->label('Importo spese contrassegno')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
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
                                    ->label('Data documento da')
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('shipment_doc_date to')
                                    ->label('Data documento a')
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->maxLength(255)
                                    ->rules(['iban'])
                                    ->columnSpan(3),
                                Forms\Components\Select::make('expense_insert_user_id')
                                    ->label('Utente inserimento spese')
                                    ->relationship('expenseInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('expense_insert_date_from')
                                    ->label('Data inserimento spese da')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('expense_insert_date_to')
                                    ->label('Data inserimento spese a')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['passive_invoice_id'] ?? null, fn ($q, $v) => $q->where('passive_invoice_id', $v))
                            ->when($data['notify_expense_amount'] ?? null, fn ($q, $v) => $q->where('notify_expense_amount', $v))
                            ->when($data['mark_expense_amount'] ?? null, fn ($q, $v) => $q->where('mark_expense_amount', $v))
                            ->when($data['reinvoice'] ?? null, fn ($q, $v) => $q->where('reinvoice', $v))
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
                                    ->label('Data pagamento da')
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('payment_date_to')
                                    ->label('Data pagamento a')
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('payment_total')
                                    ->label('Totale pagamenti')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    ->suffix('€')
                                    ->columnSpan(4),
                                Forms\Components\Select::make('payment_insert_user_id')
                                    ->label('Utente inserimento pagamento')
                                    ->relationship('paymentInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(4),
                                Forms\Components\DatePicker::make('payment_insert_date_from')
                                    ->label('Data inserimento pagamento da')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('payment_insert_date_to')
                                    ->label('Data inserimento pagamento a')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
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
                                    ->label('Data fattura emessa da')
                                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('reinvoice_date_tm')
                                    ->label('Data fattura emessa a')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('reinvoice_amount')
                                    ->label('Importo fattura emessa')
                                    ->numeric()
                                    ->visible(false)
                                    ->inputMode('decimal')
                                    ->step(0.01)
                                    ->suffix('€')
                                    ->columnSpan(4),
                                Forms\Components\Select::make('reinvoice_insert_user_id')
                                    ->label('Utente inserimento rifatturazione')
                                    ->relationship('reinvoiceInsertUser', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->optionsLimit(5)
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('reinvoice_insert_date_from')
                                    ->label('Data inserimento rifatturazione da')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('reinvoice_insert_date_to')
                                    ->label('Data inserimento rifatturazione a')
                                    ->visible(fn (): bool => Auth::user()->is_admin)
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
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->persistFiltersInSession()
            ->filtersFormWidth(MaxWidth::SevenExtraLarge)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(95vw, 1600px) !important;'
                    ]),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_act_attachment')
                        ->label('Allegato Atto')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->act_attachment_path ? Storage::url($record->act_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->act_attachment_path),             // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_notify_attachment')
                        ->label('Allegato Notifica')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::url($record->notify_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->notify_attachment_path),          // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_reinvoice_attachment')
                        ->label('Allegato Rifatturazione')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->reinvoice_attachment_path ? Storage::url($record->reinvoice_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->reinvoice_attachment_path),       // Nascondo se l'allegato non esiste
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
