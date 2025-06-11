<?php

namespace App\Filament\Company\Resources;

use App\Enums\InvoiceType;

// use App\Enums\AccrualType;
use App\Enums\TaxType;
use App\Enums\SdiStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TenderPaymentType;

use App\Filament\Company\Resources\InvoiceResource\Pages;
use App\Filament\Company\Resources\TenderResource;
use App\Filament\Company\Resources\InvoiceResource\RelationManagers;

use App\Models\AccrualType;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ManageType;
use App\Models\Tender;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static ?string $pluralModelLabel = 'Fatture';

    public static ?string $modelLabel = 'Fattura';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Grid::make('GRID')->columnSpan(3)->schema([

                    Section::make('')
                        ->columns(6)
                        ->schema([

                            Forms\Components\Select::make('invoice_type')->label('Tipo')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    if($get('client_id')!==InvoiceType::CREDIT_NOTE)
                                        $set('parent_id', null);
                                })
                                ->options(InvoiceType::class)->columnSpan(3),

                            Forms\Components\TextInput::make('invoice_uid')->label('Identificativo')
                                ->disabled()->columnSpan(3),

                            Forms\Components\TextInput::make('number')->label('Numero')
                                ->columnSpan(2)
                                ->afterStateUpdated( fn(Get $get, Set $set) => InvoiceResource::invoiceNumber($get, $set) )
                                ->live()
                                ->required()
                                ->numeric(),

                            Forms\Components\TextInput::make('section')->label('Sezionario')
                                ->columnSpan(2)
                                ->afterStateUpdated( fn(Get $get, Set $set) => InvoiceResource::invoiceNumber($get, $set) )
                                ->live()
                                ->required()
                                ->numeric(),

                            Forms\Components\TextInput::make('year')->label('Anno')
                                ->columnSpan(2)
                                ->afterStateUpdated( fn(Get $get, Set $set) => InvoiceResource::invoiceNumber($get, $set) )
                                ->live()
                                ->required()
                                ->numeric(),

                            Forms\Components\DatePicker::make('invoice_date')->label('Data')
                                ->columnSpan(2)
                                ->required(),

                            Forms\Components\TextInput::make('budget_year')->label('Anno di bilancio')
                                ->numeric()
                                ->required()->columnSpan(2),

                            Forms\Components\TextInput::make('accrual_year')->label('Anno di competenza')
                                ->numeric()
                                ->required()->columnSpan(2),

                            Forms\Components\Select::make('accrual_type_id')->label('Tipo di competenza')
                                ->required()
                                ->options(function () {
                                    return AccrualType::orderBy('order')->pluck('name', 'id');
                                })
                                ->columnSpan(3),
                            Forms\Components\Select::make('manage_type_id')->label('Tipo di gestione')
                                ->options(function () {
                                    return ManageType::orderBy('order')->pluck('name', 'id');
                                })
                                ->columnSpan(3),
                        ]),

                    Section::make('Descrizioni')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Textarea::make('description')->label('Descrizione')
                                ->required()
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('free_description')->label('Descrizione libera')
                                ->columnSpanFull(),
                        ]),



                ]),//FIRST GRID

                Grid::make('GRID')->columnSpan(2)->schema([

                    Section::make('Destinatario')
                        ->collapsible()
                        ->schema([

                            Forms\Components\Select::make('client_id')->label('Cliente')
                                ->hintAction(
                                    Action::make('Nuovo')
                                        ->icon('govicon-user-suit')
                                        ->form( fn(Form $form) => ClientResource::modalForm($form) )
                                        ->modalHeading('')
                                        ->action( fn(array $data, Client $client) => InvoiceResource::saveClient($data, $client) )
                                )
                                ->relationship(name: 'client', titleAttribute: 'denomination')
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                                )
                                ->required()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    if(empty($get('client_id')) || empty($get('tax_type')))
                                    $set('tender_id', null);
                                })
                                ->searchable('denomination')
                                ->live()
                                ->preload()
                                ->optionsLimit(5)
                                ->columns(1),

                            Forms\Components\Select::make('tax_type')->label('Entrata')
                                ->required()
                                ->options(TaxType::class)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    if(empty($get('client_id')) || empty($get('tax_type')))
                                    $set('tender_id', null);
                                })
                                ->searchable()
                                ->live()
                                ->preload()
                                ->visible(
                                    function(Get $get){
                                        if(filled ( $get('client_id') )){
                                            if(Client::find($get('client_id'))->subtype->isCompany())
                                                return false;
                                            else
                                                return true;
                                        }
                                        else
                                            return false;

                                    }
                                ),

                            Forms\Components\Select::make('tender_id')->label('Appalto')
                                ->relationship(
                                    name: 'tender',
                                    modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$get('client_id'))->where('tax_type',$get('tax_type'))
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->type->getLabel()} - CIG: {$record->cig_code}"
                                )
                                ->disabled(fn(Get $get): bool => ! filled($get('client_id')) || ! filled($get('tax_type')))
                                ->required()
                                ->searchable()
                                ->preload()
                                ->optionsLimit(5)
                                ->visible(
                                    function(Get $get){
                                        if(filled ( $get('client_id') )){
                                            if(Client::find($get('client_id'))->subtype->isCompany())
                                                return false;
                                            else
                                                return true;
                                        }
                                        else
                                            return false;

                                    }
                                )

                                ->hintAction(
                                    Action::make('Nuovo')
                                        ->icon('healthicons-f-construction-worker')
                                        ->fillForm(fn (Get $get): array => [
                                            'client_id' => $get('client_id'),
                                            'tax_type' => $get('tax_type'),
                                        ])
                                        ->form( fn(Form $form) => TenderResource::modalForm($form) )
                                        ->modalHeading('')
                                        ->action( fn(array $data, Tender $tender) => InvoiceResource::saveTender($data, $tender) )
                                ),

                            Forms\Components\Select::make('parent_id')->label('Fattura da stornare')
                                ->visible(
                                    function(Get $get, Model $record){
                                        if( filled( $get('invoice_type') ) && $get('invoice_type')===InvoiceType::CREDIT_NOTE->value )
                                            return true;
                                        else
                                            return false;

                                    }
                                )
                                ->live()
                                ->relationship(
                                    name: 'invoice',
                                    modifyQueryUsing:
                                        function (Builder $query, Get $get){
                                            $query->where('invoice_type',InvoiceType::INVOICE)
                                                ->where('client_id',$get('client_id'))
                                                ->where('year','<=',$get('year'))
                                                ->orderBy('year','desc')
                                                ->orderBy('section','desc')
                                                ->orderBy('number','desc');
                                            if(!empty($get('tax_type')))
                                                $query->where('tax_type',$get('tax_type'));
                                            if(!empty($get('tender_id')))
                                                $query->with('tender')->where('tender_id',$get('tender_id'));
                                        }
                                )
                                ->getOptionLabelFromRecordUsing(

                                    function (Model $record) {
                                        $return = "Fattura n. {$record->getInvoiceNumber()}";
                                        if($record->client->type->isPrivate())
                                            $return.= " - {$record->tax_type->getLabel()}\n{$record->tender->office_name} ({$record->tender->office_code}) - CIG: {$record->tender->cig_code}";
                                        $return.= "\nDestinatario: {$record->client->denomination}";
                                        return $return;
                                    }
                                )
                                ->preload()
                                // ->optionsLimit(10)
                                ->searchable()
                        ]),

                        Section::make('Status SDI')->columns(2)
                        ->collapsed()
                        ->schema([
                            Forms\Components\Select::make('sdi_status')->label('Ultimo status')->options(SdiStatus::class)->disabled()->columnSpanFull(),
                            Forms\Components\TextInput::make('sdi_code')->label('Codice')->readOnly()->columnSpan(1)->disabled(),
                            Forms\Components\DatePicker::make('sdi_date')->label('Data')->readOnly()->columnSpan(1)->disabled()
                                ->native(false)
                                ->displayFormat('d F Y'),
                        ]),

                        Section::make('Dati per il pagamento')->columns(4)
                        ->collapsed()
                        ->schema([
                            Forms\Components\Select::make('bank_account_id')->label('IBAN')
                                ->relationship(
                                    name: 'bankAccount',
                                    modifyQueryUsing: fn (Builder $query) =>
                                    $query->where('company_id',Filament::getTenant()->id)
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => "{$record->name}\n$record->iban"
                                )
                                ->searchable()
                                ->columnSpanFull()->preload(),
                            Forms\Components\Select::make('payment_type')->label('Tipo')
                                ->options(PaymentType::class)->columnSpan(3),
                            Forms\Components\TextInput::make('payment_days')->label('Giorni')
                                ->required()
                                ->numeric()->columnSpan(1),
                                ]),
                        Section::make('Status del pagamento')->columns(2)
                            ->collapsed()
                            ->schema([
                                Forms\Components\Select::make('payment_status')->label('Status')
                                    ->options(PaymentStatus::class)->disabled()->columnSpan(2),

                                Forms\Components\DatePicker::make('last_payment_date')->label('Data ultimo pagamento')
                                ->native(false)
                                ->displayFormat('d F Y')->columnSpan(1)->disabled(),
                                Forms\Components\TextInput::make('total_payment')->label('Totale pagamenti')
                                    ->extraInputAttributes(['style' => 'text-align: right;'])
                                    ->numeric()->suffix('€')->columnSpan(1)->disabled(),

                            ])

                ]),










                // Forms\Components\TextInput::make('check_validation')
                //     ->maxLength(255),



                // Forms\Components\TextInput::make('vat_percentage')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\TextInput::make('vat')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\Toggle::make('is_total_with_vat')
                //     ->required(),
                // Forms\Components\TextInput::make('importo')
                //     ->numeric(),
                // Forms\Components\TextInput::make('spese')
                //     ->numeric(),
                // Forms\Components\TextInput::make('rimborsi')
                //     ->numeric(),
                // Forms\Components\TextInput::make('ordinario')
                //     ->numeric(),
                // Forms\Components\TextInput::make('temporaneo')
                //     ->numeric(),
                // Forms\Components\TextInput::make('affissioni')
                //     ->numeric(),
                // Forms\Components\TextInput::make('bollo')
                //     ->numeric(),
                // Forms\Components\TextInput::make('total')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\TextInput::make('no_vat_total')
                //     ->numeric(),


                // Forms\Components\TextInput::make('pdf_path')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('xml_path')
                //     ->maxLength(255),
            ])->columns(5);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('invoice_type')->label('Tipo')
                    ->searchable()->badge()->sortable(),
                Tables\Columns\TextColumn::make('number')->label('Numero')
                    ->formatStateUsing(function ( Invoice $invoice) {
                        return $invoice->getInvoiceNumber();
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('year', $direction)
                            ->orderBy('section', $direction)
                            ->orderBy('number', $direction);
                    }),
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('invoice_date')->label('Data')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                    Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('invoice.id')->label('Fattura stornata')
                    ->formatStateUsing(function ( string $state ) {
                        $invoice = Invoice::find($state);
                        return $invoice->getInvoiceNumber();
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parent_id')->label('Id fattura stornata')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('tender.cig_code')->label('CIG')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tender.cup_code')->label('CUP')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tender.rdo_code')->label('RDO')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tax_type')->label('Entrata')
                    ->searchable()
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total')->label('Totale a doversi')
                    ->formatStateUsing(function ( Invoice $invoice) {
                        if($invoice->is_total_with_vat)
                            return $invoice->total;
                        else
                            return $invoice->no_vat_total;
                    })
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total_payment')->label('Pagamenti')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sdi_status')->label('Status')
                    ->searchable()->badge()->sortable(),
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
            ])
             ->defaultSort('id', 'desc')
            ->filters([
                //
                SelectFilter::make('invoice_type')->label('Tipo')->options(InvoiceType::class)
                    ->multiple()->searchable()->preload(),
                SelectFilter::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                    )
                    ->searchable()->preload()
                        ->optionsLimit(5),
                SelectFilter::make('tax_type')->label('Entrata')->options(TaxType::class)
                    ->multiple()->searchable()->preload(),
                SelectFilter::make('tender_id')->label('Appalto')
                    ->relationship('tender','office_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->type->getLabel()} - CIG: {$record->cig_code}"
                    )
                    ->searchable()->preload()
                        ->optionsLimit(5),
                SelectFilter::make('sdi_status')->label('Status')->options(SdiStatus::class)
                    ->multiple()->searchable()->preload(),


            ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('Export')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->openUrlInNewTab()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            echo Pdf::loadHTML(
                                Blade::render('prints/invoices_list', ['records' => $records])
                            )->stream();
                        }, 'lista_fatture_'.date('dFY').'.pdf');
                    }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
            RelationManagers\SdiNotificationsRelationManager::class,
            RelationManagers\InvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function saveTender(array $data, Tender $tender): void
    {
        $tender->company_id = Filament::getTenant()->id;
        $tender->client_id = $data['client_id'];
        $tender->tax_type = $data['tax_type'];
        $tender->type = $data['type'];
        $tender->office_name = $data['office_name'];
        $tender->office_code = $data['office_code'];
        $tender->date = $data['date'];
        $tender->cig_code = $data['cig_code'];
        $tender->cup_code = $data['cup_code'];
        $tender->rdo_code = $data['rdo_code'];
        $tender->reference_code = $data['reference_code'];
        $tender->save();
        Notification::make()
            ->title('Appalto salvato con successo')
            ->success()
            ->send();
    }

    public static function saveClient(array $data, Client $client): void
    {
        $client->company_id = Filament::getTenant()->id;
        $client->type = $data['type'];
        $client->denomination = $data['denomination'];
        $client->address = $data['address'];
        $client->city_id = $data['city_id'];
        $client->zip_code = $data['zip_code'];
        $client->tax_code = $data['tax_code'];
        $client->vat_code = $data['vat_code'];
        $client->email = $data['email'];
        $client->ipa_code = $data['ipa_code'];
        $client->save();
        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }

    public static function invoiceNumber(Get $get, Set $set){

        if(empty($get('number')) || empty($get('section')) || empty($get('year')))
            $set('invoice_uid', null);
        else{
            $number = "";
            for($i=strlen($get('number'));$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$get('number');
            $set('invoice_uid', $number."/0".$get('section')."/".$get('year'));
        }

    }
}
