<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Enums\InvoiceReference;
use App\Enums\ReversalGroupType;
use App\Enums\SdiStatus;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\AccrualType;
use App\Models\Client;
use App\Models\DocType;
use App\Models\Invoice;
use App\Models\ManageType;
use App\Models\NewContract;
use App\Models\ReversalMotivationType;
use App\Models\Sectional;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('')
            ->columns(6)
            ->schema([

                Forms\Components\Select::make('doc_type_id')->label('Tipo documento')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                        $docType = DocType::find($state);
                        if($docType?->name === 'TD00'){
                            $set('number', 0);
                            NewInvoiceResource::invoiceNumber($get, $set);
                        }
                        else if($docType?->name === 'TD99'){
                            $set('number', 0);
                            $set('year', 1901);
                            $set('budget_year', 1901);
                            $set('accrual_year', 1901);
                            $set('invoice_date', '1901-01-01');
                            NewInvoiceResource::invoiceNumber($get, $set);
                        }
                        // else if (!$docType || $docType->docGroup?->name !== 'Note di variazione') {
                        else {
                            $set('parent_id', null);
                            $set('reversal_group_type', null);
                            $set('reversal_motivation_type_id', null);
                            $set('accrual_type_id', null);
                            $set('manage_type_id', null);
                            $set('reference_date_from', '');
                            $set('reference_date_to', '');
                            $set('reference_number_from', '');
                            $set('reference_number_to', '');
                            $set('total_number', '');
                            if ($docType) {
                                $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                $set('number', $number);
                                NewInvoiceResource::invoiceNumber($get, $set);
                            }
                        }
                        NewInvoiceResource::updateDescription($get, $set, 'new_doc');
                    })
                    ->options(function (Get $get) {
                        $sectionalId = $get('sectional_id');
                        $art73 = $get('art_73');
                        if ($art73) {
                            // $docs = DocType::get();
                            // $docs = \Filament\Facades\Filament::getTenant()->docTypes();
                            $docs = Filament::getTenant()
                                        ->docTypes()
                                        ->select('doc_types.id', 'doc_types.description')
                                        ->get();
                            return $docs ? $docs->pluck('description', 'id')->toArray() : [];
                        }
                        else if (!$sectionalId) {
                            return [];
                        }
                        $sectional = Sectional::with('docTypes')->find($sectionalId);
                        return $sectional ? $sectional->docTypes->pluck('description', 'id')->toArray() : [];
                    })
                    // ->disabled(fn (Get $get) => !filled($get('sectional_id')))
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->columnSpan(4),

                Forms\Components\TextInput::make('invoice_uid')->label('Identificativo')
                    ->disabled()->columnSpan(2),

                Forms\Components\Select::make('reversal_group_type')->label('Tipo annullamento')
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
                    ->required()
                    ->live()
                    ->options(
                        collect(ReversalGroupType::cases())
                            ->filter(fn (ReversalGroupType $enum) => $enum !== ReversalGroupType::BOTH)
                            ->mapWithKeys(fn (ReversalGroupType $enum) => [$enum->value => $enum->getLabel()])
                    )
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->preload()
                    ->columnSpan(2),

                Forms\Components\Select::make('reversal_motivation_type_id')->label('Motivazione emissione nota di credito')
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
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->options(function (Get $get) {
                        $state = $get('reversal_group_type');

                        if ($state) {
                            // Trasforma la stringa nel caso dell'Enum corrispondente
                            $reversalGroupType = ReversalGroupType::tryFrom($state);

                            // Verifica che la trasformazione sia riuscita e che non sia 'both'
                            // (visto che getInverse non gestisce 'both' e andrebbe in errore)
                            if ($reversalGroupType && $reversalGroupType !== ReversalGroupType::BOTH) {

                                $options = ReversalMotivationType::where('reversal_group_type', '!=', $reversalGroupType->getInverse())
                                            ->orderBy('order')
                                            ->get();

                                return $options->pluck('name', 'id')->toArray();
                            }
                        }

                        return [];
                    })
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->columnSpan(4),

                Forms\Components\Select::make('parent_id')->label('Fattura da stornare')
                    ->visible(
                        function (Get $get) {
                            $docTypeId = $get('doc_type_id');

                            if (!filled($docTypeId)) {
                                return false;
                            }

                            $docType = DocType::with('docGroup')->find($docTypeId);

                            return $docType?->docGroup?->name === 'Note di variazione';
                            // return true;
                        }
                    )
                    ->afterStateUpdated( function($state, Get $get, Set $set){
                        $parent = Invoice::find($state);
                        $past = $parent && $parent->invoice_date
                            ? Carbon::parse($parent->invoice_date)->lt(Carbon::now()->subYear())
                            : false;
                        if($past)
                            Notification::make()
                                ->title('')
                                ->body('E\' passato più di un anno dall\'emissione della fattura da stornare<br>Gestire limite temporale ed eventuale motivazione per emettere la nota di credito')
                                ->warning()
                                ->duration(10000)
                                ->send();
                        $accepted = $parent->sdi_status == SdiStatus::ACCETTATA->value;
                        $note = DocType::find($get('doc_type_id'))->description == 'Nota di credito';
                        if($accepted && $note )
                            Notification::make()
                                ->title('')
                                ->body('Attenzione! Stai creando una nota di credito su una fattura accettata.')
                                ->warning()
                                ->duration(10000)
                                ->send();

                        if ($parent->total_payment >= $parent->total) {
                            Notification::make()
                                ->title('')
                                ->body('Attenzione! stai creando una nota di credito su una fattura pagata.')
                                ->warning()
                                ->send();

                            // Interrompi l'esecuzione dell'action
                            return;
                        }
                        NewInvoiceResource::updateDescription($get, $set, 'continue');
                    })
                    ->required(function (?Model $record, Get $get) {
                        // $privateR = ($record && $record->client?->type->isPrivate() ? true : false);
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
                            $return = "Fattura n. {$record->getNewInvoiceNumber()} del {$record->invoice_date->format('d/m/Y')}";
                            if($record->client?->type->isPublic())
                                $return.= " - {$record->tax_type->getLabel()} {$record->contract->office_name} ({$record->contract->office_code}) - CIG: {$record->contract->cig_code}";
                            // $return.= "\nDestinatario: {$record->client->denomination}";
                            return $return;
                        }
                    )
                    ->preload()
                    ->columnSpan(6)
                    // ->optionsLimit(10)
                    ->searchable(),

                // INSERIRE RIGA CON LIMITE TEMPORALE (SI/NO), MOTIVAZIONE (in tabella) (visibile SOLO se 'Nota di credito' e cliente 'Soggetto privato')
                Forms\Components\Select::make('year_limit')->label('Limite temporale (1 anno)')
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
                        'si' => 'Soggetto',
                        'no' => 'Non soggetto'
                    ])
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                        $set('number', $number);
                        NewInvoiceResource::invoiceNumber($get, $set);
                    })
                    ->live()
                    ->searchable()
                    ->preload()
                    // ->disabled(function (?Model $record) {
                    //     return $record && $record->client?->type->isPublic() ? true : false;
                    // })
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
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::invoiceNumber($get, $set))
                    ->live()
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(fn (Get $get) => !$get('art_73'))
                    ->dehydrated()
                    ->required(),

                Forms\Components\Select::make('sectional_id')->label('Sezionario')
                    ->required(fn (Get $get) => !$get('art_73'))
                    ->options(function (Get $get) {
                        $query = Sectional::where('company_id', Filament::getTenant()->id);
                        $clientId = $get('client_id');
                        if ($clientId) {
                            $client = Client::find($clientId);
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
                    ->live()
                    ->searchable()
                    ->preload()
                    ->disabled()
                    ->dehydrated()
                    ->columnSpan(2),

                Forms\Components\TextInput::make('year')->label('Anno')
                    ->columnSpan(2)
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                        $set('number', $number);
                        NewInvoiceResource::invoiceNumber($get, $set);
                        $currentYear = now()->format('Y');
                        if ($state !== $currentYear) {
                            $set('invoice_date', "{$state}-12-31");
                        } else {
                            $set('invoice_date', now()->format('Y-m-d'));
                        }
                    })
                    ->live()
                    ->debounce(1000)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(function (Get $get): bool {
                        $timingType = $get('timing_type');
                        $today = now();

                        // $contestualeCutoff = now()->copy()->startOfYear()->month(1)->day(12);
                        $contestualeCutoff = now()->copy()->startOfYear()->month(1)->day(9);

                        $differitaCutoff = now()->copy()->startOfYear()->month(1)->day(15);
                        // $differitaCutoff = now()->copy()->startOfYear()->month(1)->day(12);

                        if ($timingType === 'contestuale') {
                            return $today->gt($contestualeCutoff);
                        }

                        if ($timingType === 'differita') {
                            return $today->gt($differitaCutoff);
                        }

                        return false;
                    })
                    ->required()
                    ->numeric()
                    // ->minValue(1900)
                    ->rules(['digits:4'])
                    ->dehydrated()
                    ->default(now()->year),

                Forms\Components\DatePicker::make('invoice_date')->label('Data documento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->live()
                    ->dehydrated()
                    ->readOnly(function(Get $get) {
                        $docType = DocType::find($get('doc_type_id'));
                        return $docType?->name == 'TD99';
                    })
                    ->afterStateUpdated(function (Get $get, Set $set, $state, ?Invoice $record) {
                        if (!$state || !$get('number') || !$get('sectional_id') || !$get('year')) return;
                        $year = $get('year');
                        $date = \Illuminate\Support\Carbon::parse($state);

                        if ($date->format('Y') != $year){
                            Notification::make()
                                ->title('Incongruenza Cronologica')
                                ->body("L'anno di fatturazione ({$year}) non coincide con l'anno della data della fattura ({$date->format('Y')}).")
                                ->danger()
                                ->persistent()
                                ->send();
                        }

                        $currentNumber = (int) $get('number');
                        $sectionalId = $get('sectional_id');

                        // Creo il "peso" della fattura che sto cercando di inserire
                        $currentWeight = ($year * 1000) + $currentNumber;

                        // Cerco una fattura nello stesso sezionale che abbia:
                        // Un peso MINORE (quindi un numero precedente nello stesso anno o un anno precedente)
                        // Ma una data MAGGIORE di quella che ho appena scelto
                        $inconsistentInvoicePrec = Invoice::where('sectional_id', $sectionalId)
                            ->whereRaw('(YEAR(invoice_date) * 1000 + number) < ?', [$currentWeight])
                            ->where('invoice_date', '>', $state)
                            ->whereNotIn('sdi_status', [SdiStatus::PREAVVISO, SdiStatus::QUADRATURA])
                            ->first();

                        if ($inconsistentInvoicePrec) {
                            Notification::make()
                                ->title('Incongruenza Cronologica')
                                ->body("La fattura n. {$inconsistentInvoicePrec->number} del " .
                                    date('d/m/Y', strtotime($inconsistentInvoicePrec->invoice_date)) .
                                    " ha un numero inferiore ma una data successiva a quella inserita.")
                                ->danger()
                                ->persistent()
                                ->send();

                            // Ripristino
                            if ($record) {
                                $set('invoice_date', $record->invoice_date->format('Y-m-d'));
                            } else {
                                $set('invoice_date', null);
                            }
                        }

                        // Cerco una fattura nello stesso sezionale che abbia:
                        // Un peso MAGGIORE (quindi un numero successivo nello stesso anno o un anno successivo)
                        // Ma una data MINORE di quella che ho appena scelto
                        $inconsistentInvoiceSucc = Invoice::where('sectional_id', $sectionalId)
                            ->whereRaw('(YEAR(invoice_date) * 1000 + number) > ?', [$currentWeight])
                            ->where('invoice_date', '<', $state)
                            ->whereNotIn('sdi_status', [SdiStatus::PREAVVISO, SdiStatus::QUADRATURA])
                            ->first();

                        if ($inconsistentInvoiceSucc) {
                            Notification::make()
                                ->title('Incongruenza Cronologica')
                                ->body("La fattura n. {$inconsistentInvoiceSucc->number} del " .
                                    date('d/m/Y', strtotime($inconsistentInvoiceSucc->invoice_date)) .
                                    " ha un numero maggiore ma una data precedente a quella inserita.")
                                ->danger()
                                ->persistent()
                                ->send();

                            // Ripristino
                            if ($record) {
                                $set('invoice_date', $record->invoice_date->format('Y-m-d'));
                            } else {
                                $set('invoice_date', null);
                            }
                        }
                    })
                    ->columnSpan(2)
                    ->required()
                    ->default(now()->toDateString()),

                Forms\Components\TextInput::make('budget_year')->label('Anno di bilancio')
                    ->numeric()
                    ->required()
                    ->extraInputAttributes(['class' => 'text-right'])
                    // ->minValue(now()->subYears(11)->year)
                    ->maxValue(now()->year)
                    ->default(now()->year)
                    // ->rules(['digits:4'])
                    ->rules([
                        'digits:4',
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $currentYear = now()->year;
                                $minRecentYear = $currentYear - 11;
                                
                                // Consente il 1901 OPPURE gli anni compresi nel range
                                if (((int)$value < $minRecentYear || (int)$value > $currentYear) && (int)$value !== 1901) {
                                    $fail("L'anno di bilancio deve essere il 1901 o compreso tra {$minRecentYear} e {$currentYear}.");
                                }
                            };
                        },
                    ])
                    ->dehydrated()
                    ->readonly(function(Get $get) {
                        $docType = DocType::find($get('doc_type_id'));
                        return $docType?->name == 'TD99';
                    })
                    ->columnSpan(2),

                Forms\Components\TextInput::make('accrual_year')->label('Anno di competenza')
                    ->numeric()
                    ->required()
                    ->extraInputAttributes(['class' => 'text-right'])
                    // ->minValue(now()->subYears(11)->year)
                    ->maxValue(now()->year)
                    ->default(now()->year)
                    ->rules(['digits:4'])
                    ->rules([
                        'digits:4',
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $currentYear = now()->year;
                                $minRecentYear = $currentYear - 11;
                                
                                // Consente il 1901 OPPURE gli anni compresi nel range
                                if (((int)$value < $minRecentYear || (int)$value > $currentYear) && (int)$value !== 1901) {
                                    $fail("L'anno di bilancio deve essere il 1901 o compreso tra {$minRecentYear} e {$currentYear}.");
                                }
                            };
                        },
                    ])
                    ->dehydrated()
                    ->readOnly(function(Get $get) {
                        $docType = DocType::find($get('doc_type_id'));
                        return $docType?->name == 'TD99';
                    })
                    ->columnSpan(2),

                Forms\Components\Select::make('accrual_type_id')
                    ->label('Gestione')
                    // ->required(fn(callable $get) => $get('client_id') ? Client::find($get('client_id'))->type == ClientType::PUBLIC : true)
                    ->options(function (callable $get) {
                        $contractId = $get('contract_id');
                        if (!$contractId) {
                            return [];
                        }

                        $contract = NewContract::find($contractId);
                        if (!$contract || empty($contract->accrual_types)) {
                            return [];
                        }

                        return AccrualType::whereIn('name', $contract->accrual_types)
                            ->orderBy('order')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->columnSpan(3),

                Forms\Components\Select::make('manage_type_id')
                    ->label('Servizio')
                    // ->required(fn(callable $get) => $get('client_id') ? Client::find($get('client_id'))->type == ClientType::PUBLIC : true)
                    // ->options(function () {
                    //     return ManageType::orderBy('order')->pluck('name', 'id');
                    // })
                    ->options(function (callable $get) {
                        $contractId = $get('contract_id');
                        if (!$contractId) {
                            return [];
                        }

                        $contract = NewContract::find($contractId);
                        if (!$contract || empty($contract->manage_types)) {
                            return [];
                        }

                        return ManageType::whereIn('id', $contract->manage_types)
                            ->orderBy('order')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->columnSpan(3),
                Forms\Components\Select::make('invoice_reference')
                    ->label('Riferimento')
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD04' && DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->live()
                    ->options(InvoiceReference::class)
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'new_ref'))
                    ->preload()
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('reference_date_from')
                    ->label('Da data')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD04' && DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    // ->live()
                    ->debounce(1000)
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->visible(fn (Get $get): bool => $get('invoice_reference') !== InvoiceReference::NUMBER->value)
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('reference_date_to')
                    ->label('A data')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD04' && DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    // ->live()
                    ->debounce(1000)
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->visible(fn (Get $get): bool => $get('invoice_reference') !== InvoiceReference::NUMBER->value)
                    ->columnSpan(2),
                Placeholder::make('')
                    ->content('')
                    ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('reference_number_from')->label('Dal numero')
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD04' && DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->debounce(500)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->columnSpan(1),
                Forms\Components\TextInput::make('reference_number_to')->label('Al numero')
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD04' && DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->debounce(500)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->columnSpan(1),
                Forms\Components\TextInput::make('total_number')->label('Totali')
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD04' && DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->debounce(500)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                    ->afterStateUpdated(fn (Get $get, Set $set) => NewInvoiceResource::updateDescription($get, $set, 'continue'))
                    ->columnSpan(1),
            ]);
    }
}
