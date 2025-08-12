<?php

namespace App\Filament\Company\Resources\ClientResource\RelationManagers;

use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\PostalDocType;
use App\Enums\ProductType;
use App\Enums\TaxType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\NewContract;
use App\Models\PassiveInvoice;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
            ->columns(12)
            ->schema([
                // campi comuni
                Forms\Components\Select::make('notify_type')->label('Tipo notifica')
                    ->required()
                    ->options(NotifyType::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->autofocus()
                    ->columnSpan(2),
                Forms\Components\Select::make('contract_id')->label('Contratto')
                    ->relationship(
                        name: 'contract',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                    )
                    ->afterStateUpdated(function (Set $set, $state) {
                        $contract = NewContract::find($state);
                        $set('tax_type', $contract->tax_type);
                        $set('reinvoice', $contract->reinvoice);
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        //
                    })
                    ->columnSpan(6),
                Forms\Components\Select::make('tax_type')->label('Entrata')
                    ->required()
                    ->options(TaxType::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(2),
                Forms\Components\Toggle::make('reinvoice')
                    ->label('Rifatturare')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpan(2),
                Forms\Components\TextInput::make('order_rif')->label('Riferimento commessa')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('list_rif')->label('Riferimento distinta')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                // Forms\Components\Placeholder::make('void')
                //     ->label('')
                //     ->content('')
                //     ->columnspan(8),
                // inserire qui per caricare allegato visibile solo con 'messo'
                // Forms\Components\Placeholder::make('void')
                //     ->label('')
                //     ->content('')
                //     ->columnSpan(8),
                Forms\Components\Group::make([
                    Forms\Components\FileUpload::make('attachment')
                        ->label('Allegato')
                        ->disk('public')
                        ->directory('postal-attachments')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->maxSize(10240)
                        ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                        ->columnSpan(6),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('view_attachment')
                            ->label('Visualizza Allegato')
                            ->icon('heroicon-o-eye')
                            ->url(fn($record): ?string => $record && $record->attachment ? Storage::url($record->attachment) : null)
                            ->openUrlInNewTab()
                            ->visible(fn(Get $get, $record): bool => $get('notify_type') === NotifyType::MESSO->value && $record && $record->attachment)
                            ->disabled(fn($record): bool => !$record || !$record->attachment)
                            ->color('primary'),
                    ])->extraAttributes(['class' => 'col-span-6']), // Imposta la larghezza del contenitore Actions
                ])
                ->columnSpan(8),
                // campi usati nel caso di notify_type == 'spedizione'
                Forms\Components\DatePicker::make('s_shipment_date')->label('Data spedizione')
                    ->required()
                    ->default(now()->toDateString())
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(2),
                Forms\Components\Select::make('s_month')->label('Mese')
                    ->required()
                    ->options(Month::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(2),
                Forms\Components\Select::make('s_shipment_type_id')->label('Modalità spedizione')
                    ->options(ShipmentType::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(4),
                Forms\Components\Select::make('s_supplier_id')->label('Fornitore')
                    ->options(Supplier::pluck('denomination', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('s_passive_invoice_id', null);                                                 // reset della fattura passiva quando cambia il fornitore
                    })
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(4),
                Forms\Components\TextInput::make('s_year')->label('Anno')
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->live()
                    ->required()
                    ->rules(['digits:4'])
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->default(now()->year)
                    ->columnSpan(2),
                Forms\Components\Select::make('s_postal_doc_type')->label('Tipo documento')
                    ->required()
                    ->options(PostalDocType::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(2),
                Forms\Components\Select::make('s_product_type')->label('Tipologia spesa')
                    ->required()
                    ->options(ProductType::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(4),
                Forms\Components\TextInput::make('s_amount')->label('Importo')
                    ->required()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(3),
                Forms\Components\Select::make('s_passive_invoice_id')->label('Fattura passiva')
                    ->options(function (Get $get): array {
                        $supplierId = $get('s_supplier_id');
                        if (!$supplierId) { return []; }
                        return PassiveInvoice::where('supplier_id', $supplierId)
                            ->pluck('description', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        if($state){
                            $invoice = PassiveInvoice::find($state);
                            $set('s_passive_invoice_number', $invoice->number);
                            $set('s_passive_invoice_date', \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d'));
                            $set('s_passive_invoice_amount', number_format($invoice->total, 2, ',', '.'));
                            if($invoice->total_payment >= $invoice->total){
                                $set('s_passive_invoice_settle_date', $invoice->last_payment_date);
                            }
                            else $set('s_passive_invoice_settle_date', null);
                        }
                    })
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_supplier_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_supplier_id'))
                    ->placeholder('Seleziona prima un fornitore')
                    ->columnSpan(12),
                Forms\Components\TextInput::make('s_passive_invoice_number')->label('Numero fattura')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('s_passive_invoice_date')->label('Data fattura')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('s_passive_invoice_settle_date')->label('Data saldo')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(2),
                Forms\Components\TextInput::make('s_passive_invoice_amount')->label('Importo fattura')
                    ->required()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(3),
                // campi usati nel caso di notify_Type == 'messo'
                Forms\Components\DatePicker::make('m_notify_registration_date')->label('Data registrazione')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(2),
                Forms\Components\Select::make('m_notify_registration_user_id')->label('Registrato da')
                    ->relationship(
                        name: 'notifyRegistrationUser',
                        // modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name}"
                    )
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        //
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(4),
                Forms\Components\DatePicker::make('m_scan_import_date')->label('Data scansione file')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(2),
                Forms\Components\Select::make('m_scan_import_user_id')->label('Scansionato da')
                    ->relationship(
                        name: 'notifyRegistrationUser',
                        // modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name}"
                    )
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        //
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(4),
                Forms\Components\TextInput::make('m_send_protocol_number')->label('Protocollo invio (numero)')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('m_send_protocol_date')->label('Protocollo invio (data)')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('m_receive_protocol_number')->label('Protocollo ricezione (numero)')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(4),
                Forms\Components\DatePicker::make('m_receive_protocol_date')->label('Protocollo ricezione (data)')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(4),
                Forms\Components\TextInput::make('m_supplier')->label('Ente da rimborsare')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(4),
                Forms\Components\Select::make('m_act_type_id')->label('Tipo atto')
                    ->options(ShipmentType::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(4),
                Forms\Components\TextInput::make('m_act_year')->label('Anno atto')
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->live()
                    ->required()
                    ->rules(['digits:4'])
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->default(now()->year)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('m_recipient')->label('Destinatario / Trasgressore')
                    ->live()
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(6),
                Forms\Components\TextInput::make('m_amount')->label('Importo')
                    ->live()
                    ->required()
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('m_iban')->label('IBAN')
                    ->live()
                    ->required()
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(3),
                Forms\Components\Toggle::make('m_payed')
                    ->label('Pagato')
                    ->live()
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('m_payment_date')->label('Data pagamento')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value && $get('m_payed'))
                    ->columnSpan(3),
                Forms\Components\DatePicker::make('m_payment_insert_date')->label('Data pagamento')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value && $get('m_payed'))
                    ->columnSpan(3),
                Forms\Components\Select::make('m_payment_insert_user_id')->label('Pagamento registrato da')
                    ->relationship(
                        name: 'paymentInsertUser',
                        // modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name}"
                    )
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        //
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value && $get('m_payed'))
                    ->columnSpan(3),
                //
                Forms\Components\Select::make('reinvoice_id')->label('Fattura emessa')
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
                            $set('invoice_number', $invoice->number);
                            $set('invoice_date', \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d'));
                            $set('invoice_amount', number_format($invoice->total, 2, ',', '.'));
                        }
                    })
                    ->visible(fn(Get $get): bool => ($get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id')) || ($get('notify_type') === NotifyType::MESSO->value))
                    // ->disabled(fn(Get $get): bool => ($get('notify_type') === NotifyType::SPEDIZIONE->value && !$get('s_passive_invoice_id')) || ($get('notify_type') !== NotifyType::MESSO->value))
                    ->placeholder('Seleziona prima un fornitore')
                    ->columnSpan(12),
                Forms\Components\TextInput::make('invoice_number')->label('Numero fattura')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('reinvoice_id') !== null)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('invoice_date')->label('Data fattura')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('reinvoice_id') !== null)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('invoice_amount')->label('Importo fattura')
                    ->required()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('reinvoice_id') !== null)
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(2),
                // Forms\Components\DatePicker::make('reinvoice_insert_date')->label('Data rifatturazione')
                //     ->required()
                //     ->visible(fn(Get $get): bool => $get('reinvoice_id') !== null)
                //     ->columnSpan(3),
                Forms\Components\Select::make('reinvoice_insert_user_id')->label('Ridfatturatto da')
                    ->relationship(
                        name: 'paymentInsertUser',
                        // modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$this->getOwnerRecord()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name}"
                    )
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        //
                    })
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->visible(fn(Get $get): bool => $get('reinvoice_id') !== null)
                    ->columnSpan(3),
                Forms\Components\Textarea::make('note')->label('Note')
                        ->required()
                        ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_rif') // Modificato da 'send_number' a 'order_rif' (o altro campo appropriato)
            ->columns([
                Tables\Columns\TextColumn::make('order_rif')->label('Rif. Commessa'),
                Tables\Columns\TextColumn::make('list_rif')->label('Rif. Distinta'),
                Tables\Columns\TextColumn::make('notify_type')->label('Tipo Notifica'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(90vw, 1400px) !important;'
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(90vw, 1400px) !important;'
                    ]),
                Tables\Actions\Action::make('view_attachment')
                    ->label('Visualizza Allegato')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record): ?string => $record->attachment ? Storage::url($record->attachment) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record): bool => $record->notify_type === NotifyType::MESSO->value && $record->attachment)
                    ->disabled(fn($record): bool => !$record->attachment)
                    ->color('primary'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
