<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\RelationManagers;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\DocType;
use App\Models\Invoice;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use App\Enums\TimingType;
use App\Models\Sectional;
use App\Enums\PaymentType;
use App\Enums\VatCodeType;
use App\Models\ManageType;
use Filament\Tables\Table;
use App\Models\AccrualType;
use App\Models\NewContract;
use App\Enums\PaymentStatus;
use App\Models\InvoiceElement;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Actions\Action;
use App\Filament\Company\Resources\ClientResource;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Filament\Company\Resources\NewContractResource;
use Filament\Resources\RelationManagers\RelationManager;

class CreditNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'creditNotes';

    protected static ?string $pluralModelLabel = 'Note di credito';

    protected static ?string $modelLabel = 'Nota di credito';

    protected static ?string $title = 'Note di credito';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make('GRID')->columnSpan(2)->schema([

                    Forms\Components\Hidden::make('company_id')
                        ->default(fn () => Filament::getTenant()?->id ?? throw new \Exception('Tenant ID is missing'))
                        ->required()
                        ->dehydrated(true),

                    Section::make('Destinatario')
                        ->collapsible()
                        ->collapsed(fn (Get $get) => $get('id'))
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
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->client_id;
                                    }
                                    return null;
                                })
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
                                                CreditNotesRelationManager::invoiceNumber($get, $set);
                                            } else {
                                                $set('sectional_id', null);
                                                $set('number', null);
                                                CreditNotesRelationManager::invoiceNumber($get, $set);
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
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->tax_type;
                                    }
                                    return null;
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
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->contract_id;
                                    }
                                    return null;
                                })
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
                                        ->action( fn(array $data, NewContract $contract, Set $set) => NewContractResource::saveContract($data, $contract, $set) )
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
                                // ->afterStateUpdated( function($state){
                                //     $parent = Invoice::find($state);
                                //     $past = $parent && $parent->invoice_date
                                //         ? Carbon::parse($parent->invoice_date)->lt(Carbon::now()->subYear())
                                //         : false;
                                //     if($past)
                                //         \Filament\Notifications\Notification::make()
                                //             ->title('')
                                //             ->body('E\' passato più di un anno dall\'emissione della fattura da stornare<br>Gestire limite temporale ed eventuale motivazione per emettere la nota di credito')
                                //             ->warning()
                                //             ->duration(10000)
                                //             ->send();
                                // })
                                ->required(function (?Model $record, Get $get) {
                                    // $privateR = ($record && $record->client->type->isPrivate() ? true : false);
                                    // $client_id = $get('client_id');
                                    // $privateI = $client_id && Client::find($client_id)->type->isPrivate() ? true : false;
                                    // $private = $privateR || $privateI;
                                    $docTypeId = $get('doc_type_id');
                                    if (!filled($docTypeId)) { return false; }
                                    $docType = DocType::with('docGroup')->find($docTypeId);
                                    // $note = $docType?->docGroup?->name === 'Note di variazione';
                                    return ($docType?->docGroup?->name === 'Note di variazione');
                                })
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
                                        }
                                )
                                ->getOptionLabelFromRecordUsing(
                                    function (Model $record) {
                                        $return = "Fattura n. {$record->getNewInvoiceNumber()}";
                                        if($record->client->type->isPublic())
                                            $return.= " - {$record->tax_type->getLabel()}\n{$record->contract->office_name} ({$record->contract->office_code}) - CIG: {$record->contract->cig_code}";
                                        $return.= "\nDestinatario: {$record->client->denomination}";
                                        return $return;
                                    }
                                )
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        $past = $invoice && $invoice->invoice_date
                                            ? Carbon::parse($invoice->invoice_date)->lt(Carbon::now()->subYear())
                                            : false;
                                        if($past)
                                            \Filament\Notifications\Notification::make()
                                                ->title('')
                                                ->body('E\' passato più di un anno dall\'emissione della fattura da stornare<br>Gestire <b>limite temporale</b> ed eventuale <b>motivazione</b> per emettere la nota di credito')
                                                ->warning()
                                                ->duration(10000)
                                                ->send();
                                        return $invoice->id;
                                    }
                                    return null;
                                })
                                ->preload()
                                // ->optionsLimit(10)
                                ->searchable()
                        ]),

                        Section::make('Dati per il pagamento')->columns(4)
                            ->collapsed(fn (Get $get) => $get('id'))
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
                                    ->default(function (Get $get, Set $set) {
                                        if (blank($get('id'))) {
                                            $invoice = $this->getOwnerRecord();
                                            return $invoice->bank_account_id;
                                        }
                                        return null;
                                    })
                                    ->searchable()
                                    ->columnSpanFull()->preload(),
                                Forms\Components\Select::make('payment_type')->label('Tipo')
                                    ->default(function (Get $get, Set $set) {
                                        if (blank($get('id'))) {
                                            $invoice = $this->getOwnerRecord();
                                            return $invoice->payment_type;
                                        }
                                        return null;
                                    })
                                    ->options(PaymentType::class)->columnSpan(2),
                                Forms\Components\Select::make('payment_days')
                                    ->label('Giorni')
                                    ->required()
                                    ->default(function (Get $get, Set $set) {
                                        if (blank($get('id'))) {
                                            $invoice = $this->getOwnerRecord();
                                            return $invoice->payment_days;
                                        }
                                        return null;
                                    })
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
                        ->collapsed(fn (Get $get) => $get('id'))
                        ->schema([
                            Forms\Components\Select::make('timing_type')->label('Modalità')->options(TimingType::class)
                                ->required(fn (Get $get) => $get('timing_type') == 'differita')
                                ->placeholder(null)
                                ->default('contestuale')
                                ->live()
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('delivery_note')->label('Documento di trasporto')
                                ->required(fn (Get $get) => $get('timing_type') == 'differita')
                                ->columnSpan(2)->disabled(fn (Get $get) => $get('timing_type') != 'differita'),
                            Forms\Components\DatePicker::make('delivery_date')->label('Data documento')
                                ->required(fn (Get $get) => $get('timing_type') == 'differita')
                                ->columnSpan(2)->disabled(fn (Get $get) => $get('timing_type') != 'differita')
                                ->native(false)
                                ->displayFormat('d F Y'),
                        ]),

                    Section::make('')
                        ->columns(6)
                        ->schema([
                            Forms\Components\Select::make('doc_type_id')->label('Tipo documento')
                                ->required()
                                ->disabled()
                                ->dehydrated()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                    $docType = DocType::with('docGroup')->find($state);
                                    if (!$docType || $docType->docGroup?->name !== 'Note di variazione') {
                                        $set('parent_id', null);
                                    }
                                })
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        $doc = DocType::where('description', "Nota di credito")->first();
                                        return $doc->id;
                                    }
                                    return null;
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
                                ->afterStateHydrated(function (Get $get, Set $set) {
                                    return CreditNotesRelationManager::invoiceNumber($get, $set);
                                })
                                ->disabled()->columnSpan(3),

                            Forms\Components\Select::make('year_limit')->label('Limite temporale')
                                ->required()
                                ->visible(function (?Model $record, Get $get) {
                                    $parent = Invoice::find($get('parent_id'));
                                    $past = $parent && $parent->invoice_date
                                        ? Carbon::parse($parent->invoice_date)->lt(Carbon::now()->subYear())
                                        : false;
                                    $docTypeId = $get('doc_type_id');
                                    if (!filled($docTypeId)) { return false; }
                                    $docType = DocType::with('docGroup')->find($docTypeId);
                                    $note = $docType?->docGroup?->name === 'Note di variazione';
                                    return ($past && $note);
                                })
                                ->options([
                                    'si' => 'Si',
                                    'no' => 'No'
                                ])
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    // Update invoice number
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    CreditNotesRelationManager::invoiceNumber($get, $set);

                                    // Get the current invoice items from the repeater
                                    $invoiceItems = $get('invoiceItems') ?? [];
                                    $docTypeId = $get('doc_type_id');
                                    $docType = DocType::find($docTypeId);

                                    // Update vat_code_type for each item in the repeater
                                    $updatedItems = array_map(function ($item) use ($get,$docType, $state) {
                                        // Set vat_code_type to 'vc00' if doc_type is "Nota di credito" and year_limit is 'si'
                                        if ($docType && $docType->description === 'Nota di credito' && $state === 'si') {
                                            $item['vat_code_type'] = 'vc00';
                                        } else {
                                            // Optionally, reset to the original vat_code_type from the parent invoice item
                                            $parentInvoice = Invoice::with('invoiceItems')->find($get('parent_id'));
                                            if ($parentInvoice && $parentInvoice->invoiceItems->isNotEmpty()) {
                                                // Assuming invoice_element_id can be used to match items
                                                $parentItem = $parentInvoice->invoiceItems->firstWhere('invoice_element_id', $item['invoice_element_id']);
                                                $item['vat_code_type'] = $parentItem ? ($parentItem->vat_code_type instanceof \App\Enums\VatCodeType ? $parentItem->vat_code_type->value : $parentItem->vat_code_type) : $item['vat_code_type'];
                                            }
                                        }

                                        // Recalculate vat_amount and total based on the updated vat_code_type
                                        $rate = VatCodeType::tryFrom($item['vat_code_type'])?->getRate() / 100 ?? 0;
                                        $amount = $item['amount'] ?? 0;
                                        $vatAmount = $amount * $rate;
                                        $item['vat_amount'] = number_format($vatAmount, 2, '.', '');
                                        $item['total'] = number_format($amount + $vatAmount, 2, '.', '');

                                        return $item;
                                    }, $invoiceItems);

                                    // Set the updated invoice items back to the repeater
                                    $set('invoiceItems', $updatedItems);
                                })
                                ->live()
                                ->searchable()
                                ->preload()
                                ->disabled(function (?Model $record) {
                                    return $record && $record->client->type->isPublic() ? true : false;
                                })
                                ->columnSpan(function (?Model $record, $state) {
                                    return $state && $state == 'no' ? 2 : 6;
                                }),

                            Forms\Components\Select::make('limit_motivation_type_id')->label('Motivazione')
                                ->required()
                                ->visible(fn (Get $get) => $get('year_limit') == 'no')
                                ->options(function (Get $get) {
                                    $query = \App\Models\LimitMotivationType::where('company_id', Filament::getTenant()->id);
                                    return $query->pluck('name', 'id');
                                })
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    NewInvoiceResource::invoiceNumber($get, $set);
                                })
                                ->live()
                                ->searchable()
                                ->preload()
                                ->columnSpan(4),

                            Forms\Components\TextInput::make('number')->label('Numero')
                                ->columnSpan(2)
                                ->afterStateUpdated(fn (Get $get, Set $set) => CreditNotesRelationManager::invoiceNumber($get, $set))
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        $year = now()->year;
                                        $number = CreditNotesRelationManager::calculateNextInvoiceNumber($year, $invoice->sectional_id);
                                        return $number;
                                    }
                                    return null;
                                })
                                ->live()
                                ->disabled()
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
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->sectional_id;
                                    }
                                    return null;
                                })
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    CreditNotesRelationManager::invoiceNumber($get, $set);
                                })
                                ->live()
                                ->searchable()
                                ->preload()
                                ->disabled()
                                ->dehydrated()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('year')->label('Anno')
                                ->columnSpan(2)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    CreditNotesRelationManager::invoiceNumber($get, $set);
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
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->budget_year;
                                    }
                                    return null;
                                })
                                ->rules(['digits:4'])
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('accrual_year')->label('Anno di competenza')
                                ->numeric()
                                ->required()
                                ->minValue(1900)
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->accrual_year;
                                    }
                                    return null;
                                })
                                ->rules(['digits:4'])
                                ->columnSpan(2),

                            Forms\Components\Select::make('accrual_type_id')->label('Tipo di competenza')
                                ->required()
                                ->options(function () {
                                    return AccrualType::orderBy('order')->pluck('name', 'id');
                                })
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->accrual_type_id;
                                    }
                                    return null;
                                })
                                ->columnSpan(3),
                            Forms\Components\Select::make('manage_type_id')->label('Tipo di gestione')
                                ->options(function () {
                                    return ManageType::orderBy('order')->pluck('name', 'id');
                                })
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return $invoice->manage_type_id;
                                    }
                                    return null;
                                })
                                ->columnSpan(3),
                        ]),

                    Section::make('Descrizioni')
                        ->collapsible()
                        ->collapsed(fn (Get $get) => $get('id'))
                        ->schema([
                            Forms\Components\Textarea::make('description')->label('Descrizione')
                                ->required()
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return "A storno della fattura n. " . $invoice->getNewInvoiceNumber() . " del " . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
                                    }
                                })
                                ->rules([
                                    // Validazione personalizzata solo in creazione
                                    fn (Get $get): array => blank($get('id')) ? [
                                        function ($attribute, $value, $fail) {
                                            if (!str_contains(strtolower($value), 'totale') && !str_contains(strtolower($value), 'parziale')) {
                                                $fail("Nel campo 'Descrizione' deve essere indicato se si tratta di uno storno 'totale' o 'parziale'.");
                                                // \Filament\Notifications\Notification::make()
                                                //     ->title('Errore di validazione')
                                                //     ->body('Il campo Descrizione deve contenere la parola "totale" o "parziale".')
                                                //     ->danger()
                                                //     ->duration(5000)
                                                //     ->send();
                                            }
                                        },
                                    ] : [],
                                ])
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('free_description')->label('Descrizione libera')
                                ->required()
                                ->default(function (Get $get, Set $set) {
                                    if (blank($get('id'))) {
                                        $invoice = $this->getOwnerRecord();
                                        return "A storno della fattura n. " . $invoice->getNewInvoiceNumber() . " del " . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
                                    }
                                })
                                ->rules([
                                    // Validazione personalizzata solo in creazione
                                    fn (Get $get): array => blank($get('id')) ? [
                                        function ($attribute, $value, $fail) {
                                            if (!str_contains(strtolower($value), 'totale') && !str_contains(strtolower($value), 'parziale')) {
                                                $fail("Nel campo 'Descrizione libera' deve essere indicato se si tratta di uno storno 'totale' o 'parziale'.");
                                                // \Filament\Notifications\Notification::make()
                                                //     ->title('Errore di validazione')
                                                //     ->body('Il campo Descrizione libera deve contenere la parola "totale" o "parziale".')
                                                //     ->danger()
                                                //     ->duration(5000)
                                                //     ->send();
                                            }
                                        },
                                    ] : [],
                                ])
                                ->columnSpanFull(),
                        ]),

                ]),//FIRST GRID
                Section::make('Voci in fattura')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Repeater::make('invoiceItems')
                            ->label('')
                            ->collapsible()
                            ->collapsed()
                            ->relationship('invoiceItems')
                            ->schema([
                                Forms\Components\Select::make('invoice_element_id')
                                    ->label('Elemento')
                                    ->required()
                                    ->live()
                                    ->options(InvoiceElement::pluck('name', 'id'))
                                    ->searchable()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($state) {
                                            $el = InvoiceElement::find($state);
                                            $set('description', $el->name);
                                            $set('amount', $el->amount);
                                            $set('vat_code_type', $el->vat_code_type instanceof \App\Enums\VatCodeType ? $el->vat_code_type->value : $el->vat_code_type);

                                            // Calcolo importo IVA e totale
                                            $rate = VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;
                                            $amount = $el->amount ?? 0;
                                            $vatAmount = $amount * $rate;
                                            $total = $amount + $vatAmount;

                                            $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                                            $set('total', number_format($total, 2, '.', ''));
                                        }
                                    })
                                    ->columnSpan(4)
                                    ->preload(),
                                Forms\Components\TextInput::make('description')
                                    ->label('Descrizione')
                                    ->required()
                                    ->columnSpan(8)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Importo')
                                    ->required()
                                    ->columnSpan(4)
                                    ->prefix('€')
                                    ->maxLength(255)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        // Calcola importo IVA e totale quando amount cambia
                                        $rate = VatCodeType::tryFrom($get('vat_code_type'))?->getRate() / 100 ?? 0;
                                        $amount = $state ?? 0;
                                        $vatAmount = $amount * $rate;
                                        $total = $amount + $vatAmount;

                                        $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                                        $set('total', number_format($total, 2, '.', ''));
                                    }),
                                Forms\Components\Select::make('vat_code_type')
                                    ->label('Aliquota IVA')
                                    ->required()
                                    ->columnSpan(8)
                                    ->options(VatCodeType::class)
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        // Calcola importo IVA e totale quando vat_code_type cambia
                                        $rate = VatCodeType::tryFrom($state)?->getRate() / 100 ?? 0;
                                        $amount = $get('amount') ?? 0;
                                        $vatAmount = $amount * $rate;
                                        $total = $amount + $vatAmount;

                                        $set('vat_amount', number_format($vatAmount, 2, '.', ''));
                                        $set('total', number_format($total, 2, '.', ''));
                                    })
                                    ->preload(),
                                Forms\Components\TextInput::make('vat_amount')
                                    ->label('Importo IVA')
                                    ->readOnly()
                                    ->prefix('€')
                                    ->columnSpan(4)
                                    ->formatStateUsing(function (Get $get, Set $set) {
                                        $vatCodeType = $get('vat_code_type');
                                        $rate = $vatCodeType instanceof \App\Enums\VatCodeType ? $vatCodeType->value : $vatCodeType;
                                        $rate = VatCodeType::tryFrom($rate)?->getRate() / 100 ?? 0;
                                        $amount = $get('amount') ?? 0;
                                        $vatAmount = $amount * $rate;
                                        return number_format($vatAmount, 2, '.', '');
                                    })
                                    ->default(0.00),
                                Forms\Components\TextInput::make('total')
                                    ->label('Totale')
                                    ->readOnly()
                                    ->prefix('€')
                                    ->columnSpan(8)
                                    ->default(0.00),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string =>
                                isset($state['vat_code_type']) && ($vat = VatCodeType::tryFrom($state['vat_code_type']))
                                    ? $state['description'] . " (" . $state['amount'] . " - " . $vat->getRate() . "%)"
                                    : null
                            )
                            // ->addActionLabel('Aggiungi voce')
                            ->addable(false)
                            ->default(function (Get $get, $operation) {
                                $docTypeId = $get('doc_type_id');
                                $yearLimit = $get('year_limit');
                                if ($operation === 'create') {
                                    $parentId = $get('parent_id');
                                    if ($parentId) {
                                        $parentInvoice = Invoice::with('invoiceItems')->find($parentId);
                                        if ($parentInvoice && $parentInvoice->invoiceItems) {
                                            return $parentInvoice->invoiceItems->map(function ($item) use ($docTypeId, $yearLimit) {
                                                $add = '';
                                                // dd($docTypeId . " - " . $yearLimit);
                                                if(DocType::find($docTypeId)->description == "Nota di credito" && $yearLimit == 'si'){
                                                    $vatCodeType = 'vc00';
                                                }
                                                else
                                                    $vatCodeType = $item->vat_code_type;
                                                return [
                                                    'invoice_element_id' => $item->invoice_element_id,
                                                    'description' => $item->description,
                                                    'amount' => $item->amount,
                                                    'vat_code_type' => $vatCodeType instanceof \App\Enums\VatCodeType ? $vatCodeType->value : $vatCodeType,
                                                    'vat_amount' => number_format($item->vat_amount, 2, '.', ''),
                                                    'total' => number_format($item->total, 2, '.', ''),
                                                ];
                                            })->toArray();
                                        }
                                    }
                                }
                                return [];
                            })
                            ->afterStateUpdated(function ($record) {
                                if ($record) {
                                    $record->updateTotal();
                                    if ($record->invoice) {
                                        $record->invoice->updateTotalNotes();
                                    }
                                }
                            }),
                    ]),
            ])->columns(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('docType.description')
                //     ->label('Tipo documento')
                //     ->searchable()
                //     ->sortable(),
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
                Tables\Columns\TextColumn::make('total')->label('Importo')
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
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->modalWidth('7xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('7xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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

    public static function calculateNextInvoiceNumber($year, $sectionalId): ?int
    {
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
