<?php

namespace App\Filament\Company\Resources\ClientResource\RelationManagers;

use App\Enums\ExpenseType;
use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\ShipmentDocType;
use App\Enums\TaxType;
use App\Models\ActType;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use function PHPUnit\Framework\isNull;

class PostalExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'postalExpenses';

    protected static ?string $pluralModelLabel = 'Spese postali';

    protected static ?string $modelLabel = 'Spesa postale';

    protected static ?string $title = 'Spese postali';

    // public function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([
    //             // SEZIONE: Informazioni Base e Identificazione
    //             Forms\Components\Section::make('Informazioni di base per l\'identificazione della spesa postale')
    //                 ->icon('heroicon-o-identification')
    //                 ->collapsed(false)
    //                 ->columns(12)
    //                 ->schema([
    //                     Forms\Components\Select::make('notify_type')->label('Tipo notifica')
    //                         ->required()
    //                         ->options(NotifyType::class)
    //                         ->searchable()
    //                         ->live()
    //                         ->preload()
    //                         // ->autofocus(fn($record): bool => !$record)
    //                         ->columnSpan(3),

    //                     Forms\Components\Select::make('new_contract_id')->label('Contratto')
    //                         ->relationship(
    //                             name: 'contract',
    //                             modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
    //                         )
    //                         ->getOptionLabelFromRecordUsing(
    //                             fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
    //                         )
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             $contract = NewContract::find($state);
    //                             if ($contract) {
    //                                 // $set('tax_type', $contract->tax_type);
    //                                 $set('reinvoice', $contract->reinvoice);
    //                             }
    //                         })
    //                         ->required()
    //                         ->searchable()
    //                         ->live()
    //                         ->preload()
    //                         ->optionsLimit(5)
    //                         ->columnSpan(6),

    //                     Forms\Components\Select::make('tax_type')->label('Tipo entrata')
    //                         ->required()
    //                         ->options(TaxType::class)
    //                         ->searchable()
    //                         ->live()
    //                         ->preload()
    //                         ->columnSpan(3),
    //                 ]),

    //             // SEZIONE: Dati di Invio e Protocollo
    //             Forms\Components\Section::make('Dati relativi al protocollo di invio e alla classificazione dell\'atto inviato in lavorazione/notifica')
    //                 ->icon('heroicon-o-paper-airplane')
    //                 ->collapsed(fn($record): bool => $record && $record->shipmentInserted())
    //                 ->schema([
    //                     Forms\Components\TextInput::make('send_protocol_number')->label('Numero protocollo invio')
    //                         ->required()
    //                         ->maxLength(255)
    //                         ->default(function () {
    //                             $maxProtocolNumber = \App\Models\PostalExpense::query()
    //                                 ->selectRaw('MAX(CAST(send_protocol_number AS UNSIGNED)) as max_number')
    //                                 ->value('max_number');

    //                             return $maxProtocolNumber ? $maxProtocolNumber + 1 : 1;
    //                         }),

    //                     Forms\Components\DatePicker::make('send_protocol_date')->label('Data protocollo invio')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required()
    //                         ->default(now()->toDateString()),

    //                     Forms\Components\Select::make('shipment_type_id')->label('Modalità di invio')
    //                         ->required()
    //                         ->relationship('shipmentType', 'name')
    //                         ->searchable()
    //                         ->preload(),

    //                     Forms\Components\TextInput::make('recipient')->label('Destinatario notifica/trasgressore')
    //                         ->maxLength(255)
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
    //                         ->columnSpanFull(),

    //                     // Fornitore/Supplier condizionale
    //                     Forms\Components\Select::make('supplier_id')->label('Fornitore')
    //                         ->relationship('supplier', 'denomination')
    //                         ->searchable()
    //                         ->preload()
    //                         ->live()
    //                         ->afterStateUpdated(function (Set $set) {
    //                             $set('passive_invoice_id', null);
    //                         })
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
    //                         ->columnSpanFull(),

    //                     Forms\Components\TextInput::make('supplier_name')->label('Ente da rimborsare')
    //                         ->required()
    //                         ->maxLength(255)
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
    //                         ->columnSpanFull(),

    //                     // Gestione anni
    //                     Forms\Components\TextInput::make('manage_year')->label('Anno di gestione')
    //                         ->required()
    //                         ->numeric()
    //                         ->rules(['digits:4'])
    //                         ->default(now()->year),

    //                     Forms\Components\Select::make('act_type_id')->label('Tipo atto')
    //                         ->required()
    //                         ->relationship('actType', 'name')
    //                         ->searchable()
    //                         ->preload(),

    //                     Forms\Components\TextInput::make('act_id')->label('ID atto')
    //                         ->maxLength(255)
    //                         ->visible(false),

    //                     Forms\Components\TextInput::make('act_year')->label('Anno atto')
    //                         ->numeric()
    //                         ->rules(['digits:4'])
    //                         ->default(now()->year)
    //                         ->visible(false),

    //                     Forms\Components\FileUpload::make('act_attachment_path')->label('Allegato atto')
    //                         ->required()
    //                         ->directory('reg_richiesta')
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
    //                         // ->downloadable()
    //                         ->acceptedFileTypes(['application/pdf', 'image/*'])
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if (!empty($state)) {
    //                                 $set('act_attachment_date', now()->toDateString());
    //                             } else {
    //                                 $set('act_attachment_date', null);
    //                             }
    //                         })
    //                         ->getUploadedFileNameForStorageUsing(function (UploadedFile $file,Get $get, $record) {
    //                             // Genera un nome personalizzato per il file
    //                             $number = $get('send_protocol_number') ?? '******';                                 // numero protocollo invio
    //                             $date = $get('send_protocol_date') ?? '******';                                     // data protocollo invio
    //                             $shipmentType = ShipmentType::find($get('shipment_type_id'))->name ?? 'modalita';   // modalità invio
    //                             $client = $this->getOwnerRecord()->denomination;                                    // cliente
    //                             $taxType = TaxType::tryFrom($get('tax_type'))->getLabel() ?? '';                    // entrata
    //                             $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                      // tipo atto
    //                             $extension = $file->getClientOriginalExtension();                                   // estensione

    //                             return sprintf('%s_%s_REG-RICHIESTA_%s_%s_%s_%s.%s', $number, $date, $shipmentType, $client, $taxType, $actType, $extension);
    //                         })
    //                         ->maxSize(10240),

    //                     Forms\Components\DatePicker::make('act_attachment_date')->label('Data allegato atto')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         // ->required()
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value),
    //                         // ->visible(function (Get $get, $record): bool {
    //                         //     $hasUploadedFile = !empty($get('act_attachment_path'));
    //                         //     $hasSavedFile = $record && !empty($record->act_attachment_path);
    //                         //     return $hasUploadedFile || $hasSavedFile;
    //                         // }),

    //                     Forms\Components\Select::make('shipment_insert_user_id')->label('Utente inserimento dati')
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->shipment_insert_user_id)
    //                         ->relationship('shipmentInsertUser', 'name')
    //                         ->searchable()
    //                         ->preload()
    //                         ->optionsLimit(5),

    //                     Forms\Components\DatePicker::make('shipment_insert_date')->label('Data inserimento dati')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->shipment_insert_date),
    //                 ])
    //                 ->columns(3),

    //             // SEZIONE: Lavorazione e Notifica
    //             Forms\Components\Section::make('Dati relativi alla lavorazione/notifica richiesta ed effettuata dal fornitore incaricato')
    //                 ->icon('heroicon-o-bell-alert')
    //                 ->collapsed(fn($record): bool => $record && $record->notificationInserted())
    //                 ->visible(fn($record): bool => $record && ($record->shipment_insert_user_id && $record->shipment_insert_date))
    //                 ->schema([
    //                     Forms\Components\TextInput::make('order_rif')->label('Riferimento commessa')
    //                         ->maxLength(255),

    //                     Forms\Components\TextInput::make('list_rif')->label('Riferimento distinta')
    //                         ->maxLength(255),

    //                     Forms\Components\TextInput::make('receive_protocol_number')->label('Numero protocollo ricezione')
    //                         ->required()
    //                         ->maxLength(255)
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value),

    //                     Forms\Components\DatePicker::make('receive_protocol_date')->label('Data protocollo ricezione')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required()
    //                         ->live()
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if ($state) {
    //                                 $date = \Carbon\Carbon::parse($state);
    //                                 $set('notify_year', $date->year);
    //                                 $set('notify_month', $date->month);
    //                             }
    //                         }),

    //                     Forms\Components\TextInput::make('notify_year')->label('Anno ricezione')
    //                         ->numeric()
    //                         ->rules(['digits:4'])
    //                         ->default(now()->year),

    //                     Forms\Components\Select::make('notify_month')->label('Mese ricezione')
    //                         ->options(Month::class)
    //                         ->searchable()
    //                         ->preload(),

    //                     Forms\Components\TextInput::make('notify_amount')->label('Importo notifica')
    //                         ->required()
    //                         ->inputMode('decimal')
    //                         ->step(0.01)
    //                         ->suffix('€')
    //                         ->live()
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if ($state) {
    //                                 $set('amount_registration_date', now()->toDateString());
    //                             }
    //                         }),

    //                     Forms\Components\DatePicker::make('amount_registration_date')->label('Data registrazione importo')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required(),

    //                     Forms\Components\FileUpload::make('notify_attachment_path')->label('Allegato notifica')
    //                         ->required()
    //                         // ->autofocus(fn($record): bool => $record && $record->shipmentInserted())
    //                         ->directory('reg_post_richiesta')
    //                         ->acceptedFileTypes(['application/pdf', 'image/*'])
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if (!empty($state)) {
    //                                 $set('notify_attachment_date', now()->toDateString());
    //                             }
    //                         })
    //                         ->getUploadedFileNameForStorageUsing(function (UploadedFile $file,Get $get, $record) {
    //                             // Genera un nome personalizzato per il file
    //                             $date = $get('receive_protocol_date') ?? '******';                                                      // data protocollo ricezione
    //                             $shipmentType = ShipmentType::find($get('shipment_type_id'))->name ?? 'modalita';                       // modalità invio
    //                             $client = $this->getOwnerRecord()->denomination;                                                        // cliente
    //                             $taxType = TaxType::tryFrom($get('tax_type'))->getLabel() ?? '';                                        // entrata
    //                             $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                                          // tipo atto
    //                             $rifOrder = $get('order_rif');                                                                          // rif2 (commessa)
    //                             $rifList = $get('list_rif');                                                                            // rif2 (distinta)
    //                             $amount = ($record->notify_amount ?? 0);                                                                // importo
    //                             $extension = $file->getClientOriginalExtension();                                                       // estensione

    //                             return sprintf('%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s_%s.%s', $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $rifList, $amount, $extension);
    //                         })
    //                         ->maxSize(10240),

    //                     Forms\Components\DatePicker::make('notify_attachment_date')->label('Data allegato notifica')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         // ->required()
    //                         // ->visible(function (Get $get, $record): bool {
    //                         //     $hasUploadedFile = !empty($get('notify_attachment_path'));
    //                         //     $hasSavedFile = $record && !empty($record->notify_attachment_path);
    //                         //     return $hasUploadedFile || $hasSavedFile;
    //                         // })
    //                         ,

    //                     Forms\Components\Select::make('notify_insert_user_id')->label('Utente inserimento notifica')
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->notify_insert_user_id)
    //                         ->relationship('notifyInsertUser', 'name')
    //                         ->searchable()
    //                         ->preload()
    //                         ->optionsLimit(5),

    //                     Forms\Components\DatePicker::make('notify_insert_date')->label('Data inserimento notifica')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->notify_insert_date),
    //                 ])
    //                 ->columns(3),

    //             // SEZIONE: Gestione Spese
    //             Forms\Components\Section::make('Riferimenti alle spese della lavorazione/notifica richiesta')
    //                 ->icon('heroicon-o-currency-euro')
    //                 ->collapsed(fn($record): bool => $record && $record->expenseInserted())
    //                 ->visible(fn($record): bool => $record && ($record->notify_insert_user_id && $record->notify_insert_date))
    //                 ->schema([
    //                     // Forms\Components\Select::make('expense_type')->label('Tipologia spesa')
    //                     //     ->required()
    //                     //     ->autofocus(fn($record): bool => $record && $record->notificationInserted())
    //                     //     ->options(ExpenseType::class)
    //                     //     ->searchable()
    //                     //     ->live()
    //                     //     ->preload()
    //                     //     ->columnSpanFull(),

    //                     Forms\Components\Select::make('passive_invoice_id')->label('Fattura passiva')
    //                         ->required()
    //                         // ->relationship('passiveInvoice', 'description')
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
    //                         ->options(function (Get $get): array {
    //                             $supplierId = $get('supplier_id');
    //                             if (!$supplierId) { return []; }
    //                             return PassiveInvoice::where('supplier_id', $supplierId)
    //                                 ->pluck('description', 'id')
    //                                 ->toArray();
    //                         })
    //                         ->searchable()
    //                         ->preload()
    //                         ->live()
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if ($state) {
    //                                 $passiveInvoice = PassiveInvoice::find($state);
    //                                 $set('notify_expense_amount', $passiveInvoice->total);
    //                                 $set('shipment_doc_number', $passiveInvoice->number);
    //                                 $set('shipment_doc_date', $passiveInvoice->invoice_date->toDateString());
    //                             }
    //                         })
    //                         ->columnSpanFull(),

    //                     Forms\Components\TextInput::make('notify_expense_amount')->label('Importo spese notifica')
    //                         ->required()
    //                         ->numeric()
    //                         ->inputMode('decimal')
    //                         ->step(0.01)
    //                         ->suffix('€'),

    //                     Forms\Components\TextInput::make('mark_expense_amount')->label('Importo spese contrassegno')
    //                         ->required()
    //                         ->numeric()
    //                         ->inputMode('decimal')
    //                         ->step(0.01)
    //                         ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
    //                         ->suffix('€'),

    //                     Forms\Components\Toggle::make('reinvoice')->label('Rifatturazione spese')
    //                         ->disabled(),

    //                     Forms\Components\Select::make('shipment_doc_type')->label('Tipo documento spedizione')
    //                         ->required()
    //                         ->options(ShipmentDocType::class)
    //                         ->searchable()
    //                         ->preload(),

    //                     Forms\Components\TextInput::make('shipment_doc_number')->label('Numero documento')
    //                         ->required()
    //                         ->maxLength(255),

    //                     Forms\Components\DatePicker::make('shipment_doc_date')->label('Data documento')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required(),

    //                     Forms\Components\TextInput::make('iban')->label('IBAN')
    //                         ->maxLength(255)
    //                         ->rules(['iban']),

    //                     Forms\Components\Select::make('expense_insert_user_id')->label('Utente inserimento spese')
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->expense_insert_user_id)
    //                         ->relationship('expenseInsertUser', 'name')
    //                         ->searchable()
    //                         ->preload()
    //                         ->optionsLimit(5),

    //                     Forms\Components\DatePicker::make('expense_insert_date')->label('Data inserimento spese')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->expense_insert_date),
    //                 ])
    //                 ->columns(3),

    //             // SEZIONE: Pagamenti
    //             Forms\Components\Section::make('Informazioni relative ai pagamenti delle spese')
    //                 ->icon('heroicon-o-credit-card')
    //                 ->collapsed(fn($record): bool => $record && $record->paymentInserted())
    //                 ->visible(fn($record): bool => $record && ($record->expense_insert_user_id && $record->expense_insert_date))
    //                 ->schema([
    //                     Forms\Components\Toggle::make('payed')->label('Spese pagate')
    //                         // ->autofocus(fn($record): bool => $record && $record->expenseInserted())
    //                         ->live(),

    //                     Forms\Components\DatePicker::make('payment_date')->label('Data pagamento')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required()
    //                         ->helperText('In caso di più pagamenti, inserire la data dell\'ultimo pagamento'),

    //                     Forms\Components\TextInput::make('payment_total')->label('Totale pagamenti')
    //                         ->numeric()
    //                         ->inputMode('decimal')
    //                         ->step(0.01)
    //                         ->suffix('€'),

    //                     Forms\Components\Select::make('payment_insert_user_id')->label('Utente inserimento pagamento')
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->payment_insert_user_id)
    //                         ->relationship('paymentInsertUser', 'name')
    //                         ->searchable()
    //                         ->preload()
    //                         ->optionsLimit(5),

    //                     Forms\Components\DatePicker::make('payment_insert_date')->label('Data inserimento pagamento')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->payment_insert_date),
    //                 ])
    //                 ->columns(3),

    //             // SEZIONE: Rifatturazione
    //             Forms\Components\Section::make('Estremi della rifatturazione delle spese della lavorazione/notifica')
    //                 ->icon('heroicon-o-receipt-refund')
    //                 ->collapsed(fn($record): bool => $record && $record->reinvoiceInserted())
    //                 ->visible(fn($record): bool => $record && $record->reinvoice && ($record->payment_insert_user_id && $record->payment_insert_date))
    //                 ->schema([
    //                     Forms\Components\Select::make('reinvoice_id')->label('Fattura emessa per rifatturazione')
    //                         ->required()
    //                         // ->relationship('reInvoice', 'description')
    //                         ->options(function (Get $get): array {
    //                             return Invoice::where('client_id', $get('client_id'))
    //                                 ->whereNotNull('flow')
    //                                 ->pluck('description', 'id')
    //                                 ->toArray();
    //                         })
    //                         // ->autofocus(fn($record): bool => $record && !$record->paymentInserted())
    //                         ->searchable()
    //                         ->preload()
    //                         ->live()
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if($state){
    //                                 $invoice = Invoice::find($state);
    //                                 if ($invoice) {
    //                                     $set('reinvoice_number', $invoice->number);
    //                                     $set('reinvoice_date', $invoice->invoice_date->format('Y-m-d'));
    //                                     $set('reinvoice_amount', $invoice->total);
    //                                 }
    //                             }
    //                         })
    //                         ->columnSpanFull(),

    //                     Forms\Components\TextInput::make('reinvoice_number')->label('Numero fattura emessa')
    //                         ->required()
    //                         ->maxLength(255),

    //                     Forms\Components\DatePicker::make('reinvoice_date')->label('Data fattura emessa')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required(),

    //                     Forms\Components\TextInput::make('reinvoice_amount')->label('Importo fattura emessa')
    //                         ->required()
    //                         ->numeric()
    //                         ->inputMode('decimal')
    //                         ->step(0.01)
    //                         ->suffix('€'),

    //                     Forms\Components\Select::make('reinvoice_insert_user_id')->label('Utente inserimento rifatturazione')
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->reinvoice_insert_user_id)
    //                         ->relationship('reinvoiceInsertUser', 'name')
    //                         ->searchable()
    //                         ->preload()
    //                         ->optionsLimit(5),

    //                     Forms\Components\DatePicker::make('reinvoice_insert_date')->label('Data inserimento rifatturazione')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->reinvoice_insert_date),
    //                 ])
    //                 ->columns(3),

    //             // SEZIONE: Registrazione e Allegati
    //             Forms\Components\Section::make('Registrazione della data di lavorazione/modifica e allegati')
    //                 ->icon('heroicon-o-document-text')
    //                 ->collapsed(false)
    //                 // ->collapsed(fn($record): bool => $record && $record->reinvoiceRegistered())
    //                 // ->visible(fn($record): bool => $record && ($record->reinvoice_insert_user_id && $record->reinvoice_insert_date))
    //                 ->visible(function ($record) {
    //                     // è un invio tramite messo
    //                     $isMessenger = $record && $record->notify_type === NotifyType::MESSO;
    //                     // è una spedizione di una raccomandata con ricevuta di ritorno o di un atto giudiziario
    //                     $hasReceipt = $record && $record->notify_type === NotifyType::SPEDIZIONE && in_array(
    //                         ShipmentType::find($record->shipment_type_id)?->name,
    //                         ['Raccomandata AR' , 'Atto giudiziario']
    //                     );
    //                     // le sezioni precedenti sono state inserite
    //                     $isStep = $record && ($record->reinvoice_insert_user_id && $record->reinvoice_insert_date);
    //                     return ($isMessenger || $hasReceipt) && $isStep;
    //                 })
    //                 ->schema([
    //                     Forms\Components\DatePicker::make('notify_date_registration_date')->label('Data registrazione data di notifica')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->required(),

    //                     Forms\Components\FileUpload::make('reinvoice_attachment_path')->label('Allegato fattura emessa')
    //                         ->required()
    //                         ->directory('reg_not_db')
    //                         ->acceptedFileTypes(['application/pdf', 'image/*'])
    //                         ->maxSize(10240)
    //                         ->afterStateUpdated(function (Set $set, $state) {
    //                             if (!empty($state)) {
    //                                 $set('reinvoice_attachment_date', now()->toDateString());
    //                             } else {
    //                                 $set('reinvoice_attachment_date', null);
    //                             }
    //                         })
    //                         ->getUploadedFileNameForStorageUsing(function (UploadedFile $file,Get $get, $record) {
    //                             // Genera un nome personalizzato per il file
    //                             $date = $get('notify_date_registration_date') ?? '******';                                              // data registrazione data notifica
    //                             $client = $this->getOwnerRecord()->denomination;                                                        // cliente
    //                             $taxType = TaxType::from($get('tax_type'))->getLabel();                                                 // entrata
    //                             $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                                          // tipo atto
    //                             $extension = $file->getClientOriginalExtension();                                                       // estensione

    //                             return sprintf('%s_REG-POST-RIGHIESTA_%s_%s_%s.%s', $date, $client, $taxType, $actType, $extension);
    //                         }),
    //                         // ->autofocus(fn($record): bool => $record && $record->reinvoiceInserted()),

    //                     Forms\Components\DatePicker::make('reinvoice_attachment_date')->label('Data file fattura emessa caricato')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         // ->required()
    //                         ,

    //                     Forms\Components\Select::make('reinvoice_registration_user_id')->label('Utente registrazione')
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->reinvoice_registration_user_id)
    //                         ->relationship('reinvoiceRegistrationUser', 'name')
    //                         ->searchable()
    //                         ->preload()
    //                         ->optionsLimit(5),

    //                     Forms\Components\DatePicker::make('reinvoice_registration_date')->label('Data inserimento registrazione')
    //                         ->extraInputAttributes(['class' => 'text-center'])
    //                         ->disabled()
    //                         ->visible(fn($record): bool => $record && $record->reinvoice_registration_date),
    //                 ])
    //                 ->columns(3),

    //             // SEZIONE: Note
    //             Forms\Components\Section::make('Note')
    //                 ->icon('heroicon-o-chat-bubble-left-ellipsis')
    //                 ->collapsed(false)
    //                 ->visible()
    //                 ->schema([
    //                     Forms\Components\Textarea::make('note')->label('Note')
    //                         ->rows(3)
    //                         ->columnSpanFull(),
    //                 ]),

    //             // SEZIONE: Visualizzazione Allegati (nascosta se non ci sono allegati)
    //             Forms\Components\Section::make('Visualizza Allegati')
    //                 ->icon('heroicon-o-paper-clip')
    //                 ->collapsed()
    //                 ->visible(fn($record): bool => $record && ($record->act_attachment_path || $record->notify_attachment_path || $record->reinvoice_attachment_path))
    //                 ->schema([
    //                     Forms\Components\Actions::make([
    //                         Forms\Components\Actions\Action::make('view_act_attachment')
    //                             ->label('Visualizza Allegato Atto')
    //                             ->icon('heroicon-o-eye')
    //                             // ->url(fn($record): ?string => $record && $record->act_attachment_path ? Storage::url($record->act_attachment_path) : null)
    //                             ->url(fn($record): ?string => $record->act_attachment_path ? Storage::temporaryUrl($record->act_attachment_path,now()->addMinutes(1)) : null)
    //                             ->openUrlInNewTab()
    //                             ->visible(fn($record): bool => $record && $record->act_attachment_path)
    //                             ->color('primary'),

    //                         Forms\Components\Actions\Action::make('view_notify_attachment')
    //                             ->label('Visualizza Allegato Notifica')
    //                             ->icon('heroicon-o-eye')
    //                             // ->url(fn($record): ?string => $record && $record->notify_attachment_path ? Storage::url($record->notify_attachment_path) : null)
    //                             ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::temporaryUrl($record->notify_attachment_path,now()->addMinutes(1)) : null)
    //                             ->openUrlInNewTab()
    //                             ->visible(fn($record): bool => $record && $record->notify_attachment_path)
    //                             ->color('primary'),

    //                         Forms\Components\Actions\Action::make('view_reinvoice_attachment')
    //                             ->label('Visualizza Allegato Rifatturazione')
    //                             ->icon('heroicon-o-eye')
    //                             // ->url(fn($record): ?string => $record && $record->reinvoice_attachment_path ? Storage::url($record->reinvoice_attachment_path) : null)
    //                             ->url(fn($record): ?string => $record->reinvoice_attachment_path ? Storage::temporaryUrl($record->reinvoice_attachment_path,now()->addMinutes(1)) : null)
    //                             ->openUrlInNewTab()
    //                             ->visible(fn($record): bool => $record && $record->reinvoice_attachment_path)
    //                             ->color('primary'),
    //                     ])->columnSpanFull()
    //                 ]),
    //         ]);
    // }

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
                // SEZIONE: Informazioni Base e Identificazione
                Forms\Components\Section::make('Informazioni di base per l\'identificazione della spesa postale')
                    ->icon('heroicon-o-identification')
                    ->collapsed(false)
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('client_id')->label('Cliente')
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
                            ->getOptionLabelUsing(function (?int $value) {
                                if (!$value) {
                                    return null;
                                }
                                $record = Client::find($value);

                                if (!$record) {
                                    return null;
                                }

                                // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                                return $record->denomination;
                            })
                            ->getOptionLabelFromRecordUsing(
                                // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                fn (Model $record) => $record->denomination
                            )
                            ->required()
                            ->searchable('denomination')
                            ->live()
                            ->placeholder('Seleziona')
                            ->preload()
                            ->optionsLimit(5)
                            // ->autofocus(fn($record): bool => !$record)
                            ->columnSpan(12),

                        Forms\Components\Select::make('notify_type')->label('Tipo notifica')
                            ->required()
                            ->options(NotifyType::class)
                            ->searchable()
                            ->live()
                            ->placeholder('Seleziona')
                            ->preload()
                            ->columnSpan(3),

                        // Forms\Components\Select::make('new_contract_id')
                        //     ->label('Contratto')
                        //     ->relationship(
                        //         name: 'contract',
                        //         modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id', $get('client_id'))
                        //     )
                        //     ->getOptionLabelFromRecordUsing(
                        //         fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                        //     )
                        //     ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        //         $contract = NewContract::find($state);
                        //         if ($contract) {
                        //             $set('reinvoice', $contract->reinvoice);
                        //             // Resettiamo tax_type per evitare valori non validi
                        //             $set('tax_type', null);
                        //         } else {
                        //             $set('reinvoice', false);
                        //             $set('tax_type', null);
                        //         }
                        //     })
                        //     ->required()
                        //     ->searchable()
                        //     ->live()
                        //     ->preload()
                        //     ->optionsLimit(5)
                        //     ->columnSpan(6),

                        Forms\Components\Select::make('tax_type')
                            ->label('Tipo entrata')
                            ->required()
                            // ->options(function (Get $get) {
                            //     $contractId = $get('new_contract_id');
                            //     if (!$contractId) {
                            //         return [];
                            //     }

                            //     $contract = NewContract::find($contractId);
                            //     if (!$contract || empty($contract->getAttribute('tax_types'))) {
                            //         return [];
                            //     }

                            //     // Otteniamo i valori grezzi di tax_types (es. ["park"])
                            //     $taxTypes = $contract->getAttribute('tax_types');

                            //     // Filtra le opzioni di TaxType in base a tax_types
                            //     return collect(TaxType::cases())
                            //         ->filter(fn ($case) => in_array($case->getLabel(), $taxTypes))
                            //         ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                            //         ->toArray();
                            // })
                            ->options(function (Get $get) {
                                $clientId = $get('client_id');
                                if (empty($clientId)) {
                                    return TaxType::class;
                                }

                                // Recupera i contratti del cliente
                                $contracts = \App\Models\NewContract::where('client_id', $clientId)->get();

                                // Crea una mappa label => value per tutti i TaxType
                                $labelToValue = [];
                                foreach (TaxType::cases() as $case) {
                                    $labelToValue[strtolower($case->getLabel())] = $case->value;
                                }

                                // \Log::info('Label to Value map:', $labelToValue);

                                // Raccogli tutti i tax_types dal database
                                $taxTypesFromDb = [];
                                foreach ($contracts as $contract) {
                                    if (is_array($contract->tax_types)) {
                                        $taxTypesFromDb = array_merge($taxTypesFromDb, $contract->tax_types);
                                    }
                                }

                                // \Log::info('Tax types from DB:', $taxTypesFromDb);

                                // Converti i label in value
                                $taxTypeValues = [];
                                foreach ($taxTypesFromDb as $label) {
                                    $normalizedLabel = strtolower($label);
                                    if (isset($labelToValue[$normalizedLabel])) {
                                        $taxTypeValues[] = $labelToValue[$normalizedLabel];
                                    }
                                }

                                $taxTypeValues = array_unique(array_filter($taxTypeValues));

                                // \Log::info('Converted to values:', $taxTypeValues);

                                if (empty($taxTypeValues)) {
                                    return TaxType::class;
                                }

                                // Crea l'array di opzioni
                                $options = [];
                                foreach (TaxType::cases() as $case) {
                                    if (in_array($case->value, $taxTypeValues)) {
                                        $options[$case->value] = $case->getLabel();
                                    }
                                }

                                // \Log::info('Final options:', $options);

                                return empty($options) ? TaxType::class : $options;
                            })
                            ->searchable()
                            ->live()
                            ->placeholder('Seleziona')
                            ->preload()
                            ->columnSpan(3),

                        Forms\Components\Select::make('new_contract_id')
                            ->label('Contratto')
                            ->relationship(
                                name: 'contract',
                                modifyQueryUsing: function (Builder $query, Get $get) use ($latestDetailSubquery) {
                                    // Filtro base (mantenuto)
                                    $query->where('client_id', $get('client_id'))
                                        ->whereJsonContains('tax_types', $get('tax_type'));

                                    // 1. Aggiungi la JOIN con la subquery per l'ultima data di dettaglio
                                    $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                        $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                                    });

                                    // 2. Seleziona tutte le colonne necessarie e aggiungi l'anno calcolato (calculated_year)
                                    $query->select('new_contracts.*')
                                        ->selectRaw('
                                            COALESCE(
                                                YEAR(new_contracts.start_validity_date),
                                                YEAR(latest_details.latest_detail_date)
                                            ) AS calculated_year' // Ora usa latest_details.latest_detail_date
                                        )
                                        ->distinct();

                                    // 3. Ordina per l'anno calcolato (Ultimo Anno per Primo)
                                    $query->orderByRaw('calculated_year DESC');

                                    return $query;
                                }
                            )
                            ->getSearchResultsUsing(function (string $search, Get $get) use ($latestDetailSubquery) {
                                // 1. Pulisci la stringa di ricerca
                                $search = trim(preg_replace('/\s+/', ' ', $search));

                                // 2. Query di base e JOIN
                                $query = \App\Models\NewContract::query();

                                // Aggiungi la JOIN con la subquery (necessaria per calculated_year)
                                $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                    $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                                });

                                // Aggiungi i filtri di base del form (mantenuti)
                                $query->where('client_id', $get('client_id'));
                                // NON includere il filtro tax_types che non è presente nel modifyQueryUsing originale.

                                // 3. Applicazione del Filtro di Ricerca (CIG Code O Anno Calcolato)
                                $query->where(function ($q) use ($search) {
                                    // Cerca per CIG CODE
                                    $q->where('new_contracts.cig_code', 'LIKE', "%{$search}%");

                                    // Cerca per ANNO CALCOLATO (se il search è un numero)
                                    if (is_numeric($search)) {
                                        // Replicare la logica COALESCE per la ricerca
                                        $q->orWhereRaw('
                                            YEAR(COALESCE(
                                                new_contracts.start_validity_date,
                                                latest_details.latest_detail_date
                                            )) = ?', [$search]
                                        );
                                    }
                                });

                                // 4. Selezione delle colonne e Ordinamento (Replicata da modifyQueryUsing)
                                $query->select('new_contracts.*')
                                    ->selectRaw('
                                        COALESCE(
                                            YEAR(new_contracts.start_validity_date),
                                            YEAR(latest_details.latest_detail_date)
                                        ) AS calculated_year'
                                    )
                                    ->distinct();

                                // 5. Ordinamento corretto: Ultimo Anno per Primo
                                $query->orderByRaw('calculated_year DESC')
                                    ->orderBy('new_contracts.id', 'DESC'); // Tie-breaker

                                // 6. Esecuzione e Mappatura dei risultati (Formato armonizzato con l'anno)
                                return $query
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($record) {
                                        // Usa l'attributo calcolato per l'etichetta nel formato desiderato
                                        $label = "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}";
                                        return [$record->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelFromRecordUsing(
                                // Aggiornato per includere l'anno calcolato
                                fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}"
                            )
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                $contract = NewContract::find($state);
                                if ($contract) {
                                    $set('reinvoice', $contract->reinvoice);
                                    // Resettiamo tax_type per evitare valori non validi (logica originale mantenuta)
                                    // $set('tax_type', null);
                                } else {
                                    $set('reinvoice', false);
                                    $set('tax_type', null);
                                }
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->preload()
                            ->optionsLimit(5)
                            ->columnSpan(6),

                        // Forms\Components\Select::make('tax_type')
                        //     ->label('Tipo entrata')
                        //     ->required()
                        //     ->options(function (Get $get) {
                        //         $contractId = $get('new_contract_id');
                        //         if (!$contractId) {
                        //             return [];
                        //         }

                        //         $contract = NewContract::find($contractId);
                        //         if (!$contract || empty($contract->getAttribute('tax_types'))) {
                        //             return [];
                        //         }

                        //         // Otteniamo i valori grezzi di tax_types (es. ["park"])
                        //         $taxTypes = $contract->getAttribute('tax_types');

                        //         // Filtra le opzioni di TaxType in base a tax_types
                        //         return collect(TaxType::cases())
                        //             ->filter(fn ($case) => in_array($case->getLabel(), $taxTypes))
                        //             ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                        //             ->toArray();
                        //     })
                        //     ->searchable()
                        //     ->live()
                        //     ->placeholder('Seleziona')
                        //     ->preload()
                        //     ->columnSpan(3),
                    ]),

                // SEZIONE: Dati di Invio e Protocollo
                Forms\Components\Section::make('Dati relativi al protocollo di invio e alla classificazione dell\'atto inviato in lavorazione/notifica')
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsed(fn($record): bool => $record && $record->shipmentInserted())
                    ->schema([
                        Forms\Components\TextInput::make('send_protocol_number')->label('Numero protocollo invio')
                            ->required()
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->maxLength(255)
                            ->default(function () {
                                $maxProtocolNumber = \App\Models\PostalExpense::query()
                                    ->selectRaw('MAX(CAST(send_protocol_number AS UNSIGNED)) as max_number')
                                    ->value('max_number');

                                return $maxProtocolNumber ? $maxProtocolNumber + 1 : 1;
                            })
                            ->columnSpan(2),

                        Forms\Components\DatePicker::make('send_protocol_date')->label('Data protocollo invio')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->required()
                            ->default(now()->toDateString())
                            ->columnSpan(2),

                        Forms\Components\Select::make('shipment_type_id')->label('Modalità di invio')
                            ->required()
                            ->relationship('shipmentType', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(4),

                        Forms\Components\Select::make('send_types')->label('Tipo di spedizione')
                            ->options(SendType::pluck('name', 'id')->toArray())
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->formatStateUsing(function ($record) {
                                if ($record) {
                                    return $record->getRawOriginal('send_types')
                                        ? json_decode($record->getRawOriginal('send_types'), true)
                                        : [];
                                }
                                return [];
                            })
                            ->rules(['array', 'exists:send_types,id'])
                            ->columnSpan(4),

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
                            ->extraInputAttributes(['class' => 'text-right'])
                            // ->numeric()
                            ->rules(['digits:4'])
                            ->default(now()->year)
                            ->columnSpan(4),

                        Forms\Components\Select::make('act_type_id')->label('Tipo atto')
                            ->required()
                            ->relationship('actType', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('act_id')->label('ID atto')
                            ->maxLength(255)
                            ->visible(false),

                        Forms\Components\TextInput::make('act_year')->label('Anno atto')
                            ->numeric()
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->rules(['digits:4'])
                            ->default(now()->year)
                            ->visible(false),

                        Forms\Components\DatePicker::make('act_date')->label('Data atto')
                            ->required()
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->columnSpan(4),

                        Forms\Components\FileUpload::make('act_attachment_path')->label('Allegato atto')
                            ->required()
                            // ->disk('public')
                            ->directory('reg_richiesta')
                            // ->visibility('public')
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            // ->downloadable()
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
                                $taxType = TaxType::tryFrom($get('tax_type'))->getLabel() ?? '';                    // entrata
                                $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                      // tipo atto
                                $extension = $file->getClientOriginalExtension();                                   // estensione

                                return sprintf('%s_%s_REG-RICHIESTA_%s_%s_%s_%s.%s', $number, $date, $shipmentType, $client, $taxType, $actType, $extension);
                            })
                            ->maxSize(10240)
                            ->columnSpan(4),

                        Forms\Components\DatePicker::make('act_attachment_date')->label('Data caricamento atto')
                            ->extraInputAttributes(['class' => 'text-center'])
                            // ->required()
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            ->columnSpan(4),
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
                            ->optionsLimit(5)
                            ->columnSpan(4),

                        Forms\Components\DatePicker::make('shipment_insert_date')->label('Data inserimento dati')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->shipment_insert_date)
                            ->columnSpan(4),
                    ])
                    ->columns(12),

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
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->required()
                            ->live()
                            ->debounce(500)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $date = \Carbon\Carbon::parse($state);
                                    $set('notify_year', $date->year);
                                    $set('notify_month', $date->month);
                                }
                            }),

                        Forms\Components\TextInput::make('notify_year')->label('Anno ricezione')
                            // ->numeric()
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->rules(['digits:4'])
                            ->default(now()->year)
                            // ->readOnly()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('notify_month')->label('Mese ricezione')
                            ->options(Month::class)
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('notify_amount')->label('Importo notifica')
                            ->required()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€')
                            ->live(onBlur: true)
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->afterStateUpdated(function (Set $set, $state, $component) {
                                if ($state) {
                                    $set('amount_registration_date', now()->toDateString());
                                }
                                if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                }
                                else {
                                    $amount = $state ?? 0;
                                }
                                $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                $number = str_replace(',', '.', $clean);
                                $float = floatval($number);
                                $formatted = number_format($float, 2, ',', '.');
                                $component->state($formatted);
                            })
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state),

                        Forms\Components\DatePicker::make('amount_registration_date')->label('Data registrazione importo')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->required()
                            // ->readOnly()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\DatePicker::make('notify_date')->label('Data notifica')
                            ->extraInputAttributes(['class' => 'text-center']),

                        Forms\Components\FileUpload::make('notify_attachment_path')->label('Allegato notifica')
                            ->required()
                            // ->autofocus(fn($record): bool => $record && $record->shipmentInserted())
                            // ->disk('public')
                            ->directory('reg_post_richiesta')
                            // ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->afterStateUpdated(function (Set $set, $state) {
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
                                $taxType = TaxType::tryFrom($get('tax_type'))->getLabel() ?? '';                                        // entrata
                                $actType = ActType::find($get('act_type_id'))->name ?? 'tipo';                                          // tipo atto
                                $rifOrder = $get('order_rif');                                                                          // rif2 (commessa)
                                $rifList = $get('list_rif');                                                                            // rif2 (distinta)
                                $amount = ($record->notify_amount ?? 0);                                                                // importo
                                $extension = $file->getClientOriginalExtension();                                                       // estensione

                                return sprintf('%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s_%s.%s', $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $rifList, $amount, $extension);
                            })
                            ->maxSize(10240),

                        Forms\Components\DatePicker::make('notify_attachment_date')->label('Data caricamento notifica')
                            ->extraInputAttributes(['class' => 'text-center'])
                            // ->readOnly()
                            ->disabled()
                            ->dehydrated()
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
                            ->extraInputAttributes(['class' => 'text-center'])
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

                        // Forms\Components\Select::make('passive_invoice_id')->label('Fattura passiva')
                        //     ->required()
                        //     // ->relationship('passiveInvoice', 'description')
                        //     ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                        //     ->options(function (Get $get): array {
                        //         $supplierId = $get('supplier_id');
                        //         if (!$supplierId) { return []; }
                        //         return PassiveInvoice::where('supplier_id', $supplierId)
                        //             ->pluck('description', 'id')
                        //             ->toArray();
                        //     })
                        //     ->searchable()
                        //     ->preload()
                        //     ->live()
                        //     ->afterStateUpdated(function (Set $set, $state) {
                        //         if ($state) {
                        //             $passiveInvoice = PassiveInvoice::find($state);
                        //             $set('notify_expense_amount', $passiveInvoice->total);
                        //             $set('shipment_doc_number', $passiveInvoice->number);
                        //             $set('shipment_doc_date', $passiveInvoice->invoice_date->toDateString());
                        //         }
                        //     })
                        //     ->columnSpanFull(),

                        Forms\Components\Select::make('passive_invoice_id')
                            ->label('Fattura passiva')
                            ->required()
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                            ->options(function (Get $get): array {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) { return []; }

                                return PassiveInvoice::where('supplier_id', $supplierId)
                                    ->get()
                                    ->mapWithKeys(function ($invoice) {
                                        return [
                                            $invoice->id => sprintf(
                                                '%s del %s - %s',
                                                $invoice->number,
                                                $invoice->invoice_date->format('d/m/Y'),
                                                $invoice->description
                                            )
                                        ];
                                    })
                                    ->toArray();
                            })
                            // ->getSearchResultsUsing(function (Get $get, string $search): array {
                            //     $supplierId = $get('supplier_id');
                            //     if (!$supplierId) { return []; }

                            //     return PassiveInvoice::where('supplier_id', $supplierId)
                            //         ->where(function ($query) use ($search) {
                            //             $query->where('number', 'like', "%{$search}%")
                            //                 ->orWhere('description', 'like', "%{$search}%")
                            //                 ->orWhereDate('invoice_date', 'like', "%{$search}%");
                            //         })
                            //         ->get()
                            //         ->mapWithKeys(function ($invoice) {
                            //             return [
                            //                 $invoice->id => sprintf(
                            //                     '%s - %s (%s)',
                            //                     $invoice->number,
                            //                     $invoice->description,
                            //                     $invoice->invoice_date->format('d/m/Y')
                            //                 )
                            //             ];
                            //         })
                            //         ->toArray();
                            // })
                            ->getSearchResultsUsing(function (Get $get, string $search): array {
                                $supplierId = $get('supplier_id');
                                if (!$supplierId) { return []; }

                                // 1. Pre-processamento: Rendi tutti i separatori uno spazio
                                // Rimuovi caratteri di punteggiatura (es. , ; - /) e sostituiscili con uno spazio.
                                // Il pattern [,\;\-\/] cattura virgola, punto e virgola, trattino e slash.
                                $cleanedSearch = preg_replace('/[,\;\-\/]/', ' ', $search);

                                // 2. Suddividi in parole chiave
                                //    Rimuovi spazi multipli, trimma e dividi per spazio.
                                $keywords = collect(explode(' ', $cleanedSearch))
                                    ->map('trim')
                                    ->filter() // Rimuove stringhe vuote
                                    ->all();

                                // Se non ci sono parole chiave valide dopo la pulizia, ritorna subito
                                if (empty($keywords)) { return []; }

                                // Inizializza la query
                                $query = PassiveInvoice::where('supplier_id', $supplierId);

                                // 3. Itera su ogni parola chiave e aggiungi le condizioni WHERE
                                foreach ($keywords as $keyword) {
                                    $query->where(function ($subQuery) use ($keyword) {
                                        // Applica l'OR logico sui campi per la singola parola chiave
                                        $subQuery->where('number', 'like', "%{$keyword}%")
                                                ->orWhere('description', 'like', "%{$keyword}%")
                                                ->orWhereDate('invoice_date', 'like', "%{$keyword}%");
                                    });
                                }

                                // 4. Esegui la query finale e mappa i risultati
                                return $query->get()
                                    ->mapWithKeys(function ($invoice) {
                                        return [
                                            $invoice->id => sprintf(
                                                '%s del %s - %s',
                                                $invoice->number,
                                                $invoice->invoice_date->format('d/m/Y'),
                                                $invoice->description
                                            )
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $invoice = PassiveInvoice::find($value);
                                if (!$invoice) { return null; }

                                return sprintf(
                                    '%s del %s - %s',
                                    $invoice->number,
                                    $invoice->invoice_date->format('d/m/Y'),
                                    $invoice->description
                                );
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
                            // ->numeric()
                            ->live(onBlur: true)
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->afterStateUpdated(function ($state, $component) {
                                if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                }
                                else {
                                    $amount = $state ?? 0;
                                }
                                $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                $number = str_replace(',', '.', $clean);
                                $float = floatval($number);
                                $formatted = number_format($float, 2, ',', '.');
                                $component->state($formatted);
                            })
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\TextInput::make('mark_expense_amount')->label('Importo spese contrassegno')
                            ->required()
                            // ->numeric()
                            ->live(onBlur: true)
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->afterStateUpdated(function ($state, $component) {
                                if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                }
                                else {
                                    $amount = $state ?? 0;
                                }
                                $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                $number = str_replace(',', '.', $clean);
                                $float = floatval($number);
                                $formatted = number_format($float, 2, ',', '.');
                                $component->state($formatted);
                            })
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
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
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->required(),

                        Forms\Components\TextInput::make('iban')->label('IBAN')
                            ->maxLength(255),

                        Forms\Components\Select::make('expense_insert_user_id')->label('Utente inserimento spese')
                            ->disabled()
                            ->visible(fn($record): bool => $record && $record->expense_insert_user_id)
                            ->relationship('expenseInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('expense_insert_date')->label('Data inserimento spese')
                            ->extraInputAttributes(['class' => 'text-center'])
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
                            // ->autofocus(fn($record): bool => $record && $record->expenseInserted())
                            ->live(),

                        Forms\Components\DatePicker::make('payment_date')->label('Data pagamento')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->required()
                            ->helperText('In caso di più pagamenti, inserire la data dell\'ultimo pagamento'),

                        Forms\Components\TextInput::make('payment_total')->label('Totale pagamenti')
                            // ->numeric()
                            ->live(onBlur: true)
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->afterStateUpdated(function ($state, $component) {
                                if(str_contains($state, ',')){                                  // Se contiene una virgola
                                    $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                                }
                                else {
                                    $amount = $state ?? 0;
                                }
                                $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                                $number = str_replace(',', '.', $clean);
                                $float = floatval($number);
                                $formatted = number_format($float, 2, ',', '.');
                                $component->state($formatted);
                            })
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
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
                            ->extraInputAttributes(['class' => 'text-center'])
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
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                // Rimuovi spazi multipli e trim
                                $search = trim(preg_replace('/\s+/', ' ', $search));

                                // Query base con le stesse condizioni del relationship
                                $query = Invoice::query()
                                    // ->whereNotNull('contract_id')
                                    ->where('contract_id', $get('new_contract_id'))
                                    ->where('sdi_status', 'da_inviare')
                                    ->whereNull('parent_id');

                                // Cerca separatori (spazio, virgola, slash, trattino)
                                $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                                if (count($parts) >= 2) {
                                    // Due o più valori: prendi i primi due e convertili a integer
                                    $value1 = is_numeric($parts[0]) ? (int) $parts[0] : null;
                                    $value2 = is_numeric($parts[1]) ? (int) $parts[1] : null;

                                    if ($value1 !== null && $value2 !== null) {
                                        // Prova number/year o year/number (match esatto)
                                        $query->where(function ($q) use ($value1, $value2) {
                                            // Scenario 1: primo = number, secondo = year
                                            $q->where(function ($subQ) use ($value1, $value2) {
                                                $subQ->where('number', $value1)
                                                    ->where('year', $value2);
                                            })
                                            // Scenario 2: primo = year, secondo = number
                                            ->orWhere(function ($subQ) use ($value1, $value2) {
                                                $subQ->where('year', $value1)
                                                    ->where('number', $value2);
                                            })
                                            // Se necessario inserire qui gli scenari (7) che prevedono la ricerca multipla anche con la descrizione
                                            ;
                                        });
                                    }
                                } elseif (count($parts) === 1) {
                                    // Un solo valore: cerca SOLO match esatto in number, year o description
                                    if (is_numeric($parts[0])) {
                                        $value = (int) $parts[0];
                                        $query->where(function ($q) use ($value) {
                                            $q->where('number', $value)
                                            ->orWhere('year', $value)
                                            ->orWhere('description', $value);
                                        });
                                    }
                                }

                                return $query
                                    ->with(['client', 'sectional'])
                                    ->orderBy('invoice_date', 'desc')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($record) {
                                        $descrizione = $record->description ?? '';
                                        $sectional = $record->sectional?->description ?? 'N/A';
                                        $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                                        $year = $record->year ?? '????';
                                        $label = "{$number}/{$sectional}/{$year} - {$descrizione}";

                                        return [$record->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $record = Invoice::find($value);

                                if (!$record) {
                                    return null;
                                }

                                $descrizione = $record->description ?? '';
                                $sectional = $record->sectional?->description ?? 'N/A';
                                $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                                $year = $record->year ?? '????';

                                return "{$number}/{$sectional}/{$year} - {$descrizione}";
                            })
                            // ->autofocus(fn($record): bool => $record && !$record->paymentInserted())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if($state){
                                    $invoice = Invoice::find($state);
                                    if ($invoice) {
                                        $set('reinvoice_number', $invoice->number);
                                        $set('reinvoice_date', $invoice->invoice_date->format('Y-m-d'));
                                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                                        $number = str_replace(',', '.', $clean);
                                        $float = floatval($number);
                                        $formatted = number_format($float, 2, ',', '.');
                                        $set('reinvoice_amount', $formatted);
                                    }
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('reinvoice_number')->label('Numero fattura emessa')
                            ->required()
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('reinvoice_date')->label('Data fattura emessa')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\TextInput::make('reinvoice_amount')->label('Importo fattura emessa')
                            ->required()
                            ->live(onBlur: true)
                            ->extraInputAttributes(['class' => 'text-right'])
                            ->disabled()
                            ->dehydrated()
                            // ->numeric()
                            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
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
                            ->extraInputAttributes(['class' => 'text-center'])
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
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->required(),

                        Forms\Components\DatePicker::make('reinvoive_date')->label('Data fattura emessa')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->default(function ($record) {
                                return $record?->reInvoice?->invoice_date;
                            }),

                        Forms\Components\FileUpload::make('reinvoice_attachment_path')->label('Allegato fattura emessa')
                            ->required()
                            // ->disk('public')
                            ->directory('reg_not_db')
                            // ->visibility('public')
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
                            }),
                            // ->autofocus(fn($record): bool => $record && $record->reinvoiceInserted()),

                        Forms\Components\DatePicker::make('reinvoice_attachment_date')->label('Data caricamnto fattura emessa')
                            ->extraInputAttributes(['class' => 'text-center'])
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
                            ->extraInputAttributes(['class' => 'text-center'])
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
                                // ->url(fn($record): ?string => $record && $record->act_attachment_path ? Storage::url($record->act_attachment_path) : null)
                                ->url(fn($record): ?string => $record->act_attachment_path ? Storage::temporaryUrl($record->act_attachment_path,now()->addMinutes(1)) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->act_attachment_path)
                                ->color('primary'),

                            Forms\Components\Actions\Action::make('view_notify_attachment')
                                ->label('Visualizza Allegato Notifica')
                                ->icon('heroicon-o-eye')
                                // ->url(fn($record): ?string => $record && $record->notify_attachment_path ? Storage::url($record->notify_attachment_path) : null)
                                ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::temporaryUrl($record->notify_attachment_path,now()->addMinutes(1)) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->notify_attachment_path)
                                ->color('primary'),

                            Forms\Components\Actions\Action::make('view_reinvoice_attachment')
                                ->label('Visualizza Allegato Rifatturazione')
                                ->icon('heroicon-o-eye')
                                // ->url(fn($record): ?string => $record && $record->reinvoice_attachment_path ? Storage::url($record->reinvoice_attachment_path) : null)
                                ->url(fn($record): ?string => $record->reinvoice_attachment_path ? Storage::temporaryUrl($record->reinvoice_attachment_path,now()->addMinutes(1)) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->reinvoice_attachment_path)
                                ->color('primary'),
                        ])->columnSpanFull()
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('order_rif')
            ->columns([
                Tables\Columns\TextColumn::make('tax_type')
                    // ->badge()
                    ->label('Entrata'),
                Tables\Columns\TextColumn::make('manage_year')
                    ->label('Anno')
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('notify_type')
                    ->options(NotifyType::class),
                Tables\Filters\TernaryFilter::make('payed')
                    ->label('Pagato'),
                Tables\Filters\TernaryFilter::make('reinvoice')
                    ->label('Rifatturazione'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(95vw, 1600px) !important;'
                    ]),
            ])
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
                        ->url(fn($record): ?string => $record->act_attachment_path ? Storage::temporaryUrl($record->act_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->act_attachment_path),             // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_notify_attachment')
                        ->label('Allegato Notifica')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::temporaryUrl($record->notify_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->notify_attachment_path),          // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_reinvoice_attachment')
                        ->label('Allegato Rifatturazione')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->reinvoice_attachment_path ? Storage::temporaryUrl($record->reinvoice_attachment_path,now()->addMinutes(1)) : null)
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
}
