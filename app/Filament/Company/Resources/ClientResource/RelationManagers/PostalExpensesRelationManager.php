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

class PostalExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'postalExpenses';

    protected static ?string $pluralModelLabel = 'Spese postali';

    protected static ?string $modelLabel = 'Spesa postale';

    protected static ?string $title = 'Spese postali';

    public function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                // Campi comuni
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
                    ->columnSpan(1),
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
                    ->columnSpan(3),
                Forms\Components\Select::make('tax_type')->label('Entrata')
                    ->required()
                    ->options(TaxType::class)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(1),
                Forms\Components\Toggle::make('reinvoice')
                    ->label('Rifatturare')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpan(1),
                Forms\Components\TextInput::make('order_rif')->label('Riferimento commessa')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('list_rif')->label('Riferimento distinta')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),
                Forms\Components\Placeholder::make('void')
                    ->label('')
                    ->content('')
                    ->columnspan(4),
                // campi usati nel caso di notify_type == 'spedizione'
                Forms\Components\DatePicker::make('s_shipment_date')->label('Data spedizione')
                    ->required()
                    ->default(now()->toDateString())
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(1),
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
                    ->columnSpan(1),
                Forms\Components\Select::make('s_shipment_type_id')->label('Modalità spedizione')
                    ->options(ShipmentType::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(2),
                Forms\Components\Select::make('s_supplier_id')->label('Fornitore')
                    ->options(Supplier::pluck('denomination', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('s_passive_invoice_id', null);                                                 // reset della fattura passiva quando cambia il fornitore
                    })
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('s_year')->label('Anno')
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        //
                    })
                    ->live()
                    ->required()
                    ->rules(['digits:4'])
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->default(now()->year)
                    ->columnSpan(1),
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
                    ->columnSpan(1),
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
                    ->columnSpan(2),
                Forms\Components\TextInput::make('s_amount')->label('Importo')
                    ->required()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value)
                    ->columnSpan(1),
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
                    ->columnSpan(6),
                Forms\Components\TextInput::make('s_passive_invoice_number')->label('Numero fattura')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
                Forms\Components\DatePicker::make('s_passive_invoice_date')->label('Data fattura')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
                Forms\Components\DatePicker::make('s_passive_invoice_settle_date')->label('Data saldo')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
                Forms\Components\TextInput::make('s_passive_invoice_amount')->label('Importo fattura')
                    ->required()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
                // campi usati nel caso di notify_Type == 'messo'
                Forms\Components\TextInput::make('m_send_protocol_number')->label('Numero invio')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(1),
                Forms\Components\DatePicker::make('m_send_protocol_date')->label('Data invio')
                    ->required()
                    ->default(now()->toDateString())
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
                    ->columnSpan(1),
                // campi comuini
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
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('s_passive_invoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->placeholder('Seleziona prima un fornitore')
                    ->columnSpan(6),
                Forms\Components\TextInput::make('invoice_number')->label('Numero fattura')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('reinvoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
                Forms\Components\DatePicker::make('invoice_date')->label('Data fattura')
                    ->required()
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('reinvoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
                Forms\Components\TextInput::make('invoice_amount')->label('Importo fattura')
                    ->required()
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€')
                    ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::SPEDIZIONE->value && $get('reinvoice_id'))
                    ->disabled(fn(Get $get): bool => !$get('s_passive_invoice_id'))
                    ->columnSpan(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('send_number')
            ->columns([
                Tables\Columns\TextColumn::make('send_number'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make()->modalWidth('7xl'),
                Tables\Actions\CreateAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(90vw, 1400px) !important;'
                    ]),
            ])
            ->actions([
                // Tables\Actions\EditAction::make()->modalWidth('7xl'),
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(90vw, 1400px) !important;'
                    ]),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
