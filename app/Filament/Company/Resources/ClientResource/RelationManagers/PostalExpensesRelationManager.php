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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PostalExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'postalExpenses';

    protected static ?string $pluralModelLabel = 'Spese postali';

    protected static ?string $modelLabel = 'Spesa postale';

    protected static ?string $title = 'Spese postali';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // SEZIONE: Informazioni Base e Identificazione
                Forms\Components\Section::make('Informazioni di base per l\'identificazione della spesa postale')
                    ->icon('heroicon-o-identification')
                    ->collapsed(false)
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('notify_type')->label('Tipo notifica')
                            ->required()
                            ->options(NotifyType::class)
                            ->searchable()
                            ->live()
                            ->preload()
                            ->autofocus()
                            ->columnSpan(3),

                        Forms\Components\Select::make('new_contract_id')->label('Contratto')
                            ->relationship(
                                name: 'contract',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                            )
                            ->afterStateUpdated(function (Set $set, $state) {
                                $contract = NewContract::find($state);
                                if ($contract) {
                                    $set('tax_type', $contract->tax_type);
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
                            ->preload()
                            ->columnSpan(3),
                    ]),

                // SEZIONE: Dati di Invio e Protocollo
                Forms\Components\Section::make('Dati relativi al protocollo di invio e alla classificazione dell\'atto inviato in lavorazione/notifica')
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsed()
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
                            ->default(now()),

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

                        Forms\Components\TextInput::make('supplier')->label('Ente da rimborsare')
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
                            ->disk('public')
                            ->directory('postal-act-attachments')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                            ->maxSize(10240),

                        Forms\Components\DatePicker::make('act_attachment_date')->label('Data allegato atto')
                            ->required()
                            ->visible(function (Get $get, $record): bool {
                                $hasUploadedFile = !empty($get('act_attachment_path'));
                                $hasSavedFile = $record && !empty($record->act_attachment_path);
                                return $hasUploadedFile || $hasSavedFile;
                            }),

                        Forms\Components\Select::make('shipment_insert_user_id')->label('Utente inserimento dati')
                            ->required()
                            ->relationship('shipmentInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->default(Auth::id())
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('shipment_insert_date')->label('Data inserimento dati')
                            ->required()
                            ->default(now()),
                    ])
                    ->columns(3),

                // SEZIONE: Lavorazione e Notifica
                Forms\Components\Section::make('Dati relativi alla lavorazione/notifica richiesta ed effettuata dal fornitore incaricato')
                    ->icon('heroicon-o-bell-alert')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('notify_attachment_path')->label('Allegato notifica')
                            ->required()
                            ->disk('public')
                            ->directory('postal-notify-attachments')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240),

                        Forms\Components\DatePicker::make('notify_attachment_date')->label('Data allegato notifica')
                            ->required()
                            ->visible(function (Get $get, $record): bool {
                                $hasUploadedFile = !empty($get('notify_attachment_path'));
                                $hasSavedFile = $record && !empty($record->notify_attachment_path);
                                return $hasUploadedFile || $hasSavedFile;
                            }),

                        Forms\Components\TextInput::make('order_rif')->label('Riferimento commessa')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('list_rif')->label('Riferimento distinta')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('receive_protocol_number')->label('Numero protocollo ricezione')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value),

                        Forms\Components\DatePicker::make('receive_protocol_date')->label('Data protocollo ricezione')
                            ->required(),

                        Forms\Components\TextInput::make('notify_year')->label('Anno notifica')
                            ->numeric()
                            ->rules(['digits:4'])
                            ->default(now()->year),

                        Forms\Components\Select::make('notify_month')->label('Mese notifica')
                            ->options(Month::class)
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('notify_amount')->label('Importo notifica')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\DatePicker::make('amount_registration_date')->label('Data registrazione importo')
                            ->required(),

                        Forms\Components\Select::make('notify_insert_user_id')->label('Utente inserimento notifica')
                            ->required()
                            ->relationship('notifyInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('notify_insert_date')->label('Data inserimento notifica')
                            ->required(),
                    ])
                    ->columns(3),

                // SEZIONE: Gestione Spese
                Forms\Components\Section::make('Riferimenti alle spese della lavorazione/notifica richiesta')
                    ->icon('heroicon-o-currency-euro')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('expense_type')->label('Tipologia spesa')
                            ->required()
                            ->options(ExpenseType::class)
                            ->searchable()
                            ->live()
                            ->preload()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('passive_invoice_id')->label('Fattura passiva')
                            // ->relationship('passiveInvoice', 'description')
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
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('notify_expense_amount')->label('Importo spese notifica')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\TextInput::make('mark_expense_amount')->label('Importo spese contrassegno')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\Toggle::make('reinvoice')
                            ->label('Rifatturazione spese'),

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
                            ->relationship('expenseInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('expense_insert_date')->label('Data inserimento spese'),
                    ])
                    ->columns(3),

                // SEZIONE: Pagamenti
                Forms\Components\Section::make('Informazioni relative ai pagamenti delle spese')
                    ->icon('heroicon-o-credit-card')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('payed')
                            ->label('Spese pagate')
                            ->live(),

                        Forms\Components\DatePicker::make('payment_date')->label('Data pagamento')
                            ->helperText('In caso di più pagamenti, inserire la data dell\'ultimo pagamento'),

                        Forms\Components\TextInput::make('payment_total')->label('Totale pagamenti')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\Select::make('payment_insert_user_id')->label('Utente inserimento pagamento')
                            ->relationship('paymentInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('payment_insert_date')->label('Data inserimento pagamento'),
                    ])
                    ->columns(3),

                // SEZIONE: Rifatturazione
                Forms\Components\Section::make('Estremi della rifatturazione delle spese della lavorazione/notifica')
                    ->icon('heroicon-o-receipt-refund')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('reinvoice_id')->label('Fattura emessa per rifatturazione')
                            // ->relationship('reInvoice', 'description')
                            ->options(function (Get $get): array {
                                return Invoice::where('client_id', $this->getOwnerRecord()->id)
                                    ->whereNotNull('flow')
                                    ->pluck('description', 'id')
                                    ->toArray();
                            })
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
                            ->numeric()
                            ->inputMode('decimal')
                            ->step(0.01)
                            ->suffix('€'),

                        Forms\Components\Select::make('reinvoice_insert_user_id')->label('Utente inserimento rifatturazione')
                            ->relationship('reinvoiceInsertUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('reinvoice_insert_date')->label('Data inserimento rifatturazione'),
                    ])
                    ->columns(3),

                // SEZIONE: Registrazione e Allegati
                Forms\Components\Section::make('Registrazione della data di lavorazione/modifica e allegati')
                    ->icon('heroicon-o-document-text')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('reinvoice_attachment_path')->label('Allegato fattura emessa')
                            ->disk('public')
                            ->directory('postal-reinvoice-attachments')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240),

                        Forms\Components\DatePicker::make('reinvoice_attachment_date')->label('Data file fattura emessa caricato'),

                        Forms\Components\DatePicker::make('notify_date_registration_date')->label('Data registrazione data di notifica'),

                        Forms\Components\Select::make('reinvoice_registration_user_id')->label('Utente registrazione')
                            ->relationship('reinvoiceRegistrationUser', 'name')
                            ->searchable()
                            ->preload()
                            ->optionsLimit(5),

                        Forms\Components\DatePicker::make('reinvoice_registration_date')->label('Data inserimento registrazione'),
                    ])
                    ->columns(3),

                // SEZIONE: Note
                Forms\Components\Section::make('Note')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->collapsed()
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
                                ->color('secondary'),

                            Forms\Components\Actions\Action::make('view_reinvoice_attachment')
                                ->label('Visualizza Allegato Rifatturazione')
                                ->icon('heroicon-o-eye')
                                ->url(fn($record): ?string => $record && $record->reinvoice_attachment_path ? Storage::url($record->reinvoice_attachment_path) : null)
                                ->openUrlInNewTab()
                                ->visible(fn($record): bool => $record && $record->reinvoice_attachment_path)
                                ->color('success'),
                        ])->columnSpanFull()
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_rif')
            ->columns([
                Tables\Columns\TextColumn::make('notify_type')
                    ->label('Tipo Notifica')
                    ->badge(),
                Tables\Columns\TextColumn::make('order_rif')
                    ->label('Rif. Commessa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('list_rif')
                    ->label('Rif. Distinta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract.office_name')
                    ->label('Contratto')
                    ->limit(30),
                Tables\Columns\TextColumn::make('recipient')
                    ->label('Destinatario')
                    ->limit(20),
                Tables\Columns\TextColumn::make('notify_amount')
                    ->label('Importo')
                    ->money('EUR'),
                Tables\Columns\IconColumn::make('payed')
                    ->label('Pagato')
                    ->boolean(),
                Tables\Columns\IconColumn::make('reinvoice')
                    ->label('Rifatturato')
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
                        ->url(fn($record): ?string => $record->act_attachment_path ? Storage::url($record->act_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->act_attachment_path),             // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_notify_attachment')
                        ->label('Allegato Notifica')
                        ->icon('heroicon-o-bell')
                        ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::url($record->notify_attachment_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->notify_attachment_path),          // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_reinvoice_attachment')
                        ->label('Allegato Rifatturazione')
                        ->icon('heroicon-o-receipt-tax')
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
}
