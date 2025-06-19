<?php

namespace App\Filament\Company\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Tender;
use App\Models\DocType;
use App\Models\Invoice;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Enums\SdiStatus;
use Filament\Forms\Form;
use App\Models\Sectional;
use App\Enums\InvoiceType;
use App\Enums\PaymentType;
use App\Models\ManageType;
use App\Models\NewInvoice;
use Filament\Tables\Table;
use App\Models\AccrualType;
use App\Models\NewContract;
use App\Enums\PaymentStatus;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\NewInvoiceResource\Pages;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\InvoiceItemsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\ActivePaymentsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\SdiNotificationsRelationManager;

class NewInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static ?string $pluralModelLabel = 'Fatture';

    public static ?string $modelLabel = 'Fattura';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make('GRID')->columnSpan(2)->schema([

                    Section::make('Destinatario')
                        ->collapsible()
                        ->schema([

                            Forms\Components\Select::make('client_id')->label('Cliente')
                                ->hintAction(
                                    Action::make('Nuovo')
                                        ->icon('govicon-user-suit')
                                        ->form(fn (Form $form) => ClientResource::modalForm($form))
                                        ->modalHeading('')
                                        ->action(fn (array $data, Client $client, Set $set) => NewInvoiceResource::saveClient($data, $client, $set))
                                )
                                ->relationship(name: 'client', titleAttribute: 'denomination')
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                )
                                ->required()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('contract_id', null);
                                    $set('sectional_id', null);
                                    $set('tax_type', null);
                                    $clientId = $get('client_id');
                                    if ($clientId) {
                                        $client = \App\Models\Client::find($clientId);
                                        if ($client && $client->type) {
                                            $sectional = \App\Models\Sectional::where('company_id', Filament::getTenant()->id)
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
                                    }
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
                                    $set('contract_id', null);
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

                            Forms\Components\Select::make('contract_id')->label('Contratto')
                                ->relationship(
                                    name: 'contract',
                                    modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$get('client_id'))->where('tax_type',$get('tax_type'))
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                                )
                                ->disabled(fn(Get $get): bool => ! filled($get('client_id')) || ! filled($get('tax_type')))
                                ->required()
                                ->searchable()
                                ->live()
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
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    if ($state) {
                                        $contract = \App\Models\NewContract::find($state);
                                        $set('accrual_type_id', $contract ? $contract->accrual_type_id : null);
                                    } else {
                                        $set('accrual_type_id', null);
                                    }
                                })
                                ->hintAction(
                                    Action::make('Nuovo')
                                        ->icon('govicon-file-contract-o')
                                        ->fillForm(fn (Get $get): array => [
                                            'client_id' => $get('client_id'),
                                            'tax_type' => $get('tax_type'),
                                        ])
                                        ->form( fn(Form $form) => NewContractResource::modalForm($form) )
                                        ->modalWidth('7xl')
                                        ->modalHeading('')
                                        ->action( fn(array $data, NewContract $contract) => NewContractResource::saveContract($data, $contract) )
                                ),

                            Forms\Components\Select::make('parent_id')->label('Fattura da stornare')
                                ->visible(
                                    function (Get $get) {
                                        $docTypeId = $get('doc_type_id');

                                        if (!filled($docTypeId)) {
                                            return false;
                                        }

                                        $docType = DocType::with('docGroup')->find($docTypeId);

                                        return $docType?->docGroup?->name === 'Note di variazione';
                                    }
                                )
                                ->live()
                                ->relationship(
                                    name: 'invoice',
                                    modifyQueryUsing:
                                        function (Builder $query, Get $get){
                                            $query->whereHas('docType.docGroup', function ($query) {
                                                    $query->whereIn('name', ['Fatture', 'Autofatture']);
                                                })
                                                ->where('client_id',$get('client_id'))
                                                ->where('year','<=',$get('year'))
                                                ->orderBy('year','desc')
                                                ->orderBy('sectional_id','desc')
                                                ->orderBy('number','desc');
                                            if(!empty($get('tax_type')))
                                                $query->where('tax_type',$get('tax_type'));
                                            if(!empty($get('tender_id')))
                                                $query->with('tender')->where('tender_id',$get('tender_id'));
                                        }
                                )
                                ->getOptionLabelFromRecordUsing(
                                    function (Model $record) {
                                        $return = "Fattura n. {$record->getNewInvoiceNumber()}";
                                        if($record->client->type->isPrivate())
                                            $return.= " - {$record->tax_type->getLabel()}\n{$record->contract->office_name} ({$record->contract->office_code}) - CIG: {$record->contract->cig_code}";
                                        $return.= "\nDestinatario: {$record->client->denomination}";
                                        return $return;
                                    }
                                )
                                ->preload()
                                // ->optionsLimit(10)
                                ->searchable()
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
                                ->options(PaymentType::class)->columnSpan(2),
                            Forms\Components\Select::make('payment_days')
                                ->label('Giorni')
                                ->required()
                                ->options([
                                    30 => '30',
                                    60 => '60',
                                    90 => '90',
                                    120 => '120',
                                ])
                                ->columnSpan(2),
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
                Grid::make('GRID')->columnSpan(3)->schema([

                    Section::make('')
                        ->columns(6)
                        ->schema([

                            Forms\Components\Select::make('doc_type_id')->label('Tipo')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                    $docType = DocType::with('docGroup')->find($state);
                                    if (!$docType || $docType->docGroup?->name !== 'Note di variazione') {
                                        $set('parent_id', null);
                                    }
                                })
                                ->options(function (Get $get) {
                                    $sectionalId = $get('sectional_id');
                                    if (!$sectionalId) {
                                        return [];
                                    }
                                    $sectional = Sectional::with('docTypes')->find($sectionalId);
                                    return $sectional ? $sectional->docTypes->pluck('description', 'id')->toArray() : [];
                                })
                                ->disabled(fn (Get $get) => !filled($get('sectional_id')))
                                ->searchable()
                                ->preload()
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('invoice_uid')->label('Identificativo')
                                ->disabled()->columnSpan(3),

                            Forms\Components\TextInput::make('number')->label('Numero')
                                ->columnSpan(2)
                                ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::invoiceNumber($get, $set))
                                ->live()
                                ->dehydrated()
                                ->required(),

                            Forms\Components\Select::make('sectional_id')->label('Sezionario')
                                ->required()
                                ->options(function (Get $get) {
                                    $query = \App\Models\Sectional::where('company_id', Filament::getTenant()->id);
                                    $clientId = $get('client_id');
                                    if ($clientId) {
                                        $client = \App\Models\Client::find($clientId);
                                        if ($client && $client->type) {
                                            $query->where('client_type', $client->type->value);
                                        }
                                    }
                                    return $query->pluck('description', 'id');
                                })
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);

                                    NewInvoiceResource::invoiceNumber($get, $set);
                                })
                                ->afterStateHydrated(function (Get $get, Set $set) {
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    NewInvoiceResource::invoiceNumber($get, $set);
                                })
                                ->live()
                                ->searchable()
                                ->preload()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('year')->label('Anno')
                                ->columnSpan(2)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);

                                    NewInvoiceResource::invoiceNumber($get, $set);
                                })
                                ->live()
                                ->required()
                                ->numeric()
                                ->minValue(1900)
                                ->rules(['digits:4'])
                                ->default(now()->year),

                            Forms\Components\DatePicker::make('invoice_date')->label('Data')
                                ->columnSpan(2)
                                ->required()
                                ->default(now()->toDateString()),

                            Forms\Components\TextInput::make('budget_year')->label('Anno di bilancio')
                                ->numeric()
                                ->required()
                                ->minValue(1900)
                                ->rules(['digits:4'])
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('accrual_year')->label('Anno di competenza')
                                ->numeric()
                                ->required()
                                ->minValue(1900)
                                ->rules(['digits:4'])
                                ->columnSpan(2),

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
                                ->required()
                                ->columnSpanFull(),
                        ]),



                ]),//FIRST GRID
            ])->columns(5);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Invoice::newInvoices())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('docType.description')
                    ->label('Tipo documento')
                    ->searchable()
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
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_date')->label('Data')
                    ->date('d/m/Y')
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
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
                    ->searchable()
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total')->label('Totale a doversi')
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
                SelectFilter::make('doc_type_id')
                    ->label('Tipo documento')
                    ->options(function () {
                        return DocType::orderBy('doc_group_id')->pluck('description', 'id')->toArray();
                    })
                    ->multiple()
                    ->searchable()
                    ->columnSpan(2)
                    ->preload(),
                SelectFilter::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                    )
                    ->searchable()->preload()
                        ->optionsLimit(5),
                SelectFilter::make('tax_type')->label('Entrata')->options(TaxType::class)
                    ->multiple()->searchable()->preload(),
                SelectFilter::make('contract_id')->label('Contratto')
                    ->relationship('contract','office_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                    )
                    ->searchable()->preload()
                    ->optionsLimit(5),
                SelectFilter::make('sdi_status')->label('Status')->options(SdiStatus::class)
                    ->multiple()->searchable()->preload(),


            ])->filtersFormColumns(2)
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
            SdiNotificationsRelationManager::class,
            InvoiceItemsRelationManager::class,
            ActivePaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewInvoices::route('/'),
            'create' => Pages\CreateNewInvoice::route('/create'),
            'edit' => Pages\EditNewInvoice::route('/{record}/edit'),
        ];
    }

    // public static function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['flow'] = 'out';
    //     return $data;
    // }

    public static function saveClient(array $data, Client $client, Set $set): void
    {
        $client->company_id = Filament::getTenant()->id;
        $client->type = $data['type'];
        $client->subtype = $data['subtype'];
        $client->denomination = $data['denomination'];
        $client->address = $data['address'];
        $client->city_id = $data['city_id'];
        $client->tax_code = $data['tax_code'];
        $client->vat_code = $data['vat_code'];
        $client->email = $data['email'];
        $client->save();

        $set('client_id', $client->id);

        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }

    public static function saveContract(array $data, NewContract $contract): void
    {
        $contract->company_id = Filament::getTenant()->id;
        $contract->client_id = $data['client_id'];
        $contract->tax_type = $data['tax_type'];
        $contract->start_validity_date = $data['start_validity_date'];
        $contract->end_validity_date = $data['end_validity_date'];
        $contract->accrual_type_id = $data['accrual_type_id'];
        $contract->payment_type = $data['payment_type'];
        $contract->cig_code = $data['cig_code'];
        $contract->cup_code = $data['cup_code'];
        $contract->office_code = $data['office_code'];
        $contract->office_name = $data['office_name'];
        $contract->amount = $data['amount'];
        $contract->save();
        Notification::make()
            ->title('Contratto salvato con successo')
            ->success()
            ->send();
    }

    public static function invoiceNumber(Get $get, Set $set){

        if(empty($get('number')) || empty($get('sectional_id')) || empty($get('year')))
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

        if ($year && $sectionalId) {
            $maxNumber = \App\Models\Invoice::where('year', $year)
                ->where('sectional_id', $sectionalId)
                ->where('company_id', Filament::getTenant()->id)
                ->max('number');

            if ($maxNumber !== null) {
                return $maxNumber + 1;
            }

            $sectional = \App\Models\Sectional::find($sectionalId);
            return $sectional?->progressive;
        }

        return null;
    }
}
