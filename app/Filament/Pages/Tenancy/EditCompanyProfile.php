<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\City;
use App\Models\User;
use App\Models\State;
use App\Enums\FundType;
use App\Models\DocType;
use Filament\Forms\Form;
use App\Enums\VatCodeType;
use App\Enums\TaxRegimeType;
use App\Enums\LiquidationType;
use App\Enums\ShareholderType;
use App\Enums\WithholdingType;
use App\Models\InvoiceElement;
use Filament\Facades\Filament;
use App\Enums\PaymentReasonType;
use App\Enums\TransactionType;
use Filament\Forms\Components\Tabs;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Tenancy\EditTenantProfile;

class EditCompanyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Dati azienda';
    }

    public function form(Form $form): Form
    {
        $company = filament()->getTenant();
        $user = Auth::user();
        $isManager = $user && $company ? $user->isManagerOf($company) : false;
        $italyId = State::where('name', 'Italy')->first()->id;
        return $form
            ->columns(12)
            ->extraAttributes(['class' => 'w-full'])
            ->disabled(!$isManager)
            ->schema([
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(11),
                // Toggle::make('is_active')->label('Attiva')
                //     ->columnSpan(1),
                Tabs::make('')
                    ->tabs([
                        Tabs\Tab::make('Informazioni')
                            ->schema([
                                TextInput::make('name')->label('Denominazione - Cognome e Nome')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(6),
                                TextInput::make('vat_number')->label('Partita Iva')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('tax_number')->label('Codice fiscale')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Select::make('state_id')->label('Paese')
                                    ->options(State::all()->pluck('name', 'id')->toArray())
                                    ->placeholder('')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->default($italyId)
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        //
                                    })
                                    ->columnspan(2),
                                TextInput::make('city_code')
                                    ->label('Codice catastale')
                                    ->required()
                                    ->maxLength(4)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $city = City::where('code', $state)->first();
                                        if ($city) {
                                            $set('city', $city->code);
                                        } else {
                                            $set('city', null);
                                        }
                                    })
                                    ->columnSpan(2),
                                TextInput::make('address')->label('Indirizzo')
                                    ->maxLength(255)
                                    ->required()
                                    ->columnSpan(6),
                                Select::make('city')->label('Città')
                                    ->relationship(name: 'city', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (callable $get) => $get('state_id') == $italyId)
                                    ->columnSpan(4),
                                TextInput::make('place')->label('Città')
                                    ->required()
                                    ->maxLength(255)
                                    ->visible(fn (callable $get) => $get('state_id') != $italyId)
                                    ->columnspan(4),
                                TextInput::make('phone')->label('Telefono')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                TextInput::make('email')->label('Email')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                TextInput::make('pec')->label('Pec')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                TextInput::make('fax')->label('Fax')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Albo professionale')
                            ->schema([
                                TextInput::make('register')->label('Albo professionale')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Select::make('register_province_id')->label('Provincia Albo')
                                    ->relationship(name: 'registerProvince', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),
                                TextInput::make('register_number')->label('Numero')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                DatePicker::make('register_date')->label('Data iscrizione')
                                    ->columnSpan(3),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('REA')
                            ->schema([
                                Select::make('rea_province_id')->label('Ufficio')
                                    ->relationship(name: 'reaProvince', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(5),
                                Placeholder::make('')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(1),
                                TextInput::make('rea_number')->label('Numero')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Placeholder::make('')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(1),
                                TextInput::make('nominal_capital')->label('Capitale sociale')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Placeholder::make('')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(6),
                                Select::make('shareholders')
                                    ->label('Socio unico')
                                    ->options(ShareholderType::options())
                                    ->columnSpan(3),
                                Select::make('liquidation')
                                    ->label('Stato liquidazione')
                                    ->options(LiquidationType::options())
                                    ->columnSpan(3),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Responsabili')
                            ->schema([
                                Fieldset::make('Responsabile conservazione')
                                    ->relationship('curator')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nome')
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        TextInput::make('surname')
                                            ->label('Cognome')
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        TextInput::make('tax_code')
                                            ->label('Codice fiscale')
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->columnSpan(6),
                                        TextInput::make('pec')
                                            ->label('Pec')
                                            ->maxLength(255)
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12),
                                Fieldset::make('Responsabile produttore')
                                    ->relationship('productor')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nome')
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        TextInput::make('surname')
                                            ->label('Cognome')
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        TextInput::make('tax_code')
                                            ->label('Codice fiscale')
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->columnSpan(6),
                                        TextInput::make('pec')
                                            ->label('Pec')
                                            ->maxLength(255)
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12)
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Profilo fiscale')
                            ->schema([
                                Fieldset::make('Regime fiscale')
                                    ->relationship('fiscalProfile')
                                    ->schema([
                                        Select::make('tax_regime')
                                            ->label('')
                                            ->options(
                                                collect(TaxRegimeType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->columnSpan(8)
                                    ])
                                    ->columns(12),
                                // Fieldset::make('Esigibilità IVA')
                                //     ->relationship('fiscalProfile')
                                //     ->schema([
                                //         Forms\Components\Toggle::make('vat_enforce')
                                //             ->label('Attiva')
                                //             ->reactive()
                                //             ->columnSpan(1),
                                //         Placeholder::make('')->content('')->columnSpan(1),
                                //         Select::make('vat_enforce_type')
                                //             ->label('')
                                //             ->options(
                                //                 collect(VatEnforceType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                //             )
                                //             ->visible(fn ($get) => $get('vat_enforce'))
                                //             ->columnSpan(6),
                                //     ])
                                //     ->columns(12)
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Previdenza')
                            ->schema([
                                Repeater::make('socialContributions')
                                    ->label('Cassa previdenziale')
                                    ->relationship('socialContributions')
                                    ->schema([
                                        Select::make('fund')
                                            ->label('Tipo cassa')
                                            ->options(
                                                collect(FundType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->required()
                                            ->columnSpan(7),
                                        Placeholder::make('')
                                            ->label('')
                                            ->content('')
                                            ->columnSpan(5),
                                        TextInput::make('rate')
                                            ->label('Aliquota cassa')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix('%')
                                            ->columnSpan(2),
                                        TextInput::make('taxable_perc')
                                            ->label('su')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix("% dell'imponibile")
                                            ->columnSpan(3),
                                        Select::make('vat_code')
                                            ->label('Codice IVA')
                                            ->options(
                                                collect(VatCodeType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->required()
                                            ->columnSpan(7),
                                    ])
                                    ->columns(12)
                                    ->maxItems(3)
                                    ->defaultItems(0)
                                    ->addActionLabel('Aggiungi Cassa previdenziale')
                                    ->deleteAction(
                                        fn ($action) => $action->label('Rimuovi Cassa previdenziale')
                                    )
                                    ->columnSpan(12),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Ritenute')
                            ->schema([
                                Placeholder::make('')
                                    ->content("Inserisci in questa sezione i dati relativi alla ritenuta d'acconto ed alle ritenute previdenziali da applicare di default alle tue fatture, nel caso in cui la tua cassa previdenziale di appartenenza vi sia soggetta.")
                                    ->columnSpan(12),
                                Repeater::make('withholdings')
                                    ->label('Ritenuta')
                                    ->relationship('withholdings')
                                    ->schema([
                                        Select::make('withholding_type')
                                            ->label('Tipo ritenuta')
                                            ->options(
                                                collect(WithholdingType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->required()
                                            ->columnSpan(7),
                                        Placeholder::make('')
                                            ->label('')
                                            ->content('')
                                            ->columnSpan(5),
                                        TextInput::make('rate')
                                            ->label('Aliquota ritenuta')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix('%')
                                            ->columnSpan(2),
                                        TextInput::make('taxable_perc')
                                            ->label('su')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix("% dell'imponibile")
                                            ->columnSpan(3),
                                        Select::make('payment_reason')
                                            ->label('Causale pagamento')
                                            ->options(
                                                collect(PaymentReasonType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->required()
                                            ->searchable()
                                            ->columnSpan(7),
                                    ])
                                    ->columns(12)
                                    ->maxItems(4)
                                    ->defaultItems(0)
                                    ->addActionLabel('Aggiungi Ritenuta')
                                    ->deleteAction(
                                        fn ($action) => $action->label('Rimuovi Ritenuta')
                                    )
                                    ->columnSpan(12),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Gestione bolli')
                            ->schema([
                                Section::make("Aggiungi automaticamente l'imposta di bollo nella fatture quando gli importi esenti IVA sono uguali o superiori al valore indicato.")
                                    ->relationship('stampDuty')
                                    ->extraAttributes(['style' => 'font-weight: normal;'])
                                    ->schema([
                                        Placeholder::make('')
                                            ->content("Fatture elettroniche")
                                            ->columnSpan(6)->extraAttributes(['style' => 'font-weight: bold; font-size: 1.25rem;']),
                                        Placeholder::make('')
                                            ->content("Bollo virtuale")
                                            ->columnSpan(6)->extraAttributes(['style' => 'font-weight: bold; font-size: 1.25rem;']),
                                        Toggle::make('active')
                                            ->label('Attiva')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (!$state) {
                                                    $set('value', '77.47');
                                                    $set('add_row', false);
                                                    $set('row_description', null);
                                                }
                                            })
                                            ->columnSpan(6),
                                        Toggle::make('virtual_stamp')
                                            ->label('Attiva')
                                            ->reactive()
                                            ->columnSpan(6),
                                        // Placeholder::make('')->content('')->columnSpan(11),
                                        Placeholder::make('')
                                            ->content("Importo")
                                            ->columnSpan(1)->extraAttributes(['style' => 'font-weight: bold;']),
                                        TextInput::make('value')
                                            ->label('')
                                            ->suffix('€')
                                            ->disabled(fn ($get) => !$get('active'))
                                            ->dehydrated(true)
                                            ->default('77.47')
                                            ->columnSpan(3),
                                        Placeholder::make('')
                                            ->content("")
                                            ->columnSpan(2),
                                        Placeholder::make('')
                                            ->content("Importo")
                                            ->columnSpan(1)->extraAttributes(['style' => 'font-weight: bold;']),
                                        TextInput::make('virtual_amount')
                                            ->label('')
                                            ->suffix('€')
                                            ->disabled(fn ($get) => !$get('virtual_stamp'))
                                            ->dehydrated(true)
                                            ->default('2.00')
                                            ->columnSpan(3),
                                        Placeholder::make('')
                                            ->content("Addebita il costo del bollo al cliente aggiungendo una riga nella fattura elettronica")
                                            ->columnSpan(12)->extraAttributes(['style' => 'font-weight: bold; font-size: 1.25rem;']),
                                        Toggle::make('add_row')
                                            ->label('Attiva')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (!$state) {
                                                    $set('row_description', null);
                                                    $set('amount', null);
                                                }
                                            })
                                            ->disabled(fn ($get) => !$get('active'))
                                            ->dehydrated(true)
                                            ->columnSpan(1),
                                        Placeholder::make('')->content('')->columnSpan(11),
                                        Placeholder::make('')
                                            ->content("Descrizione riga da aggiungere nella fattura elettronica")
                                            ->columnSpan(8)->extraAttributes(['style' => 'font-weight: bold;']),
                                        Placeholder::make('')
                                            ->content("Importo")
                                            ->columnSpan(4)->extraAttributes(['style' => 'font-weight: bold;']),
                                        TextInput::make('row_description')
                                            ->label('')
                                            ->disabled(fn ($get) => !$get('add_row'))
                                            ->dehydrated(true)
                                            ->columnSpan(8),
                                        TextInput::make('amount')
                                            ->label('')
                                            ->disabled(fn ($get) => !$get('add_row'))
                                            ->dehydrated(true)
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12)
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Documenti')
                            ->schema([
                                CheckboxList::make('docTypes')
                                    ->label('')
                                    ->relationship('docTypes', 'id')
                                    ->options(fn () => DocType::flatOptions())
                                    ->bulkToggleable()
                                    ->columnSpan(12),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Sezionari')
                            ->schema([
                                Repeater::make('sectionals')
                                    ->label('')
                                    ->relationship('sectionals')
                                    ->schema([
                                        Select::make('client_type')
                                            ->label('Tipo cliente')
                                            ->options(
                                                collect(\App\Enums\ClientType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->required()
                                            ->columnSpan(3),
                                        TextInput::make('description')
                                            ->label('Sezionario')
                                            ->maxLength(255)
                                            ->required()
                                            ->columnSpan(1),
                                        Select::make('numeration_type')
                                            ->label('Tipo numerazione')
                                            ->options(
                                                collect(\App\Enums\NumerationType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->live()
                                            ->required()
                                            ->columnSpan(3),
                                        TextInput::make('progressive')
                                            ->label('Numero progressivo')
                                            ->maxLength(255)
                                            ->required()
                                            ->columnSpan(2),
                                        CheckboxList::make('doc_type_ids')
                                            ->label('Tipi documento')
                                            // ->options(function ($livewire, $record) {
                                            //     $companyId = null;
                                            //     if ($record && $record->company_id) {
                                            //         $companyId = $record->company_id;
                                            //     } elseif ($livewire->getRecord()) {
                                            //         $companyId = $livewire->getRecord()->id;
                                            //     }
                                            //     return $companyId ? \App\Models\DocType::groupedOptions($companyId) : [];
                                            // })
                                            // ->relationship(
                                            //     name: 'docTypes',
                                            //     titleAttribute: null,
                                            //     modifyQueryUsing: function ($query, $record, $livewire) {
                                            //         $companyId = null;
                                            //         if ($record && $record->company_id) {
                                            //             $companyId = $record->company_id;
                                            //         } elseif ($livewire->getRecord()) {
                                            //             $companyId = $livewire->getRecord()->id;
                                            //         }
                                            //         if ($companyId) {
                                            //             $query->whereIn('doc_types.id', function ($subQuery) use ($companyId) {
                                            //                 $subQuery->select('doc_type_id')
                                            //                     ->from('company_docs')
                                            //                     ->where('company_id', $companyId);
                                            //             })->orderBy('doc_types.name', 'asc');
                                            //         }
                                            //     }
                                            // )
                                            // ->options(function ($livewire) {
                                            //     $companyId = $livewire->company->id ?? null;
                                            //     return $companyId
                                            //         ? \App\Models\DocType::groupedOptions($companyId)
                                            //         : [];
                                            // })
                                            // ->relationship(
                                            //     name: 'docTypes',
                                            //     titleAttribute: null,
                                            //     modifyQueryUsing: function ($query, $record, $livewire) {
                                            //         $companyId = $livewire->company->id ?? null;
                                            //         if ($companyId) {
                                            //             $query->whereIn('doc_types.id', function ($subQuery) use ($companyId) {
                                            //                 $subQuery->select('doc_type_id')
                                            //                     ->from('company_docs')
                                            //                     ->where('company_id', $companyId);
                                            //             })->orderBy('doc_types.name', 'asc');
                                            //         }
                                            //     }
                                            // )
                                            ->options($company ? DocType::groupedOptions($company->id) : [])
                                            ->relationship(
                                                name: 'docTypes',
                                                titleAttribute: null,
                                                modifyQueryUsing: function ($query) use ($company) {
                                                    if ($company) {
                                                        $query->whereIn('doc_types.id', function ($subQuery) use ($company) {
                                                            $subQuery->select('doc_type_id')
                                                                ->from('company_docs')
                                                                ->where('company_id', $company->id);
                                                        })->orderBy('doc_types.name', 'asc');
                                                    }
                                                }
                                            )
                                            ->getOptionLabelFromRecordUsing(function ($record) {
                                                $groupName = $record->docGroup ? $record->docGroup->name : 'Senza gruppo';
                                                return "{$record->name} - {$record->description} ({$groupName})";
                                            })
                                            ->getOptionLabelFromRecordUsing(function ($record) {
                                                $groupName = $record->docGroup ? $record->docGroup->name : 'Senza gruppo';
                                                return "{$record->name} - {$record->description} ({$groupName})";
                                            })
                                            // ->placeholder('Salva l’azienda per visualizzare i tipi di documento disponibili')
                                            ->required()
                                            ->columnSpan(6),
                                    ])
                                    ->columns(12)
                                    ->maxItems(10)
                                    ->defaultItems(0)
                                    ->addActionLabel('Aggiungi Sezionario')
                                    ->deleteAction(
                                        fn ($action) => $action->label('Rimuovi Sezionario')
                                    )
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(function (array $state): ?string {
                                        $clientTypeLabel = isset($state['client_type']) && $state['client_type'] !== null
                                            ? \App\Enums\ClientType::tryFrom($state['client_type'])?->getLabel() ?? 'Senza tipo'
                                            : 'Senza tipo';
                                        $description = $state['description'] ?? 'Senza descrizione';
                                        return "$description ($clientTypeLabel)";
                                    })
                                    ->columnSpan(12),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Voci in fattura')
                            ->schema([
                                Repeater::make('invoicesElements')
                                    ->label('')
                                    ->relationship('invoicesElements')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Voce')
                                            ->maxLength(255)
                                            ->required()
                                            ->columnSpan(4),
                                        TextInput::make('description')
                                            ->label('Descrizione')
                                            ->maxLength(255)
                                            ->columnSpan(8),
                                        Select::make('transaction_type')
                                            ->label('Tipo di transazione')
                                            ->options(
                                                collect(TransactionType::cases())->mapWithKeys(fn ($case) => [
                                                    $case->value => $case->getLabel(),
                                                ])->toArray()
                                            )
                                            ->columnSpan(4),
                                        TextInput::make('quantity')->label('Quantità')
                                            ->columnSpan(2)
                                            ->numeric(),
                                        TextInput::make('measure_unit')->label('Unità di misura')
                                            ->columnSpan(2)
                                            ->maxLength(255),
                                        // Placeholder::make('')
                                        //     ->columnSpan(3),
                                        TextInput::make('unit_price')
                                            ->label('Prezzo unitario')
                                            ->columnSpan(3),
                                            // ->default(0.00),
                                        TextInput::make('amount')
                                            ->label('Importo')
                                            ->required()
                                            ->default(0.00)
                                            ->columnSpan(3),
                                        Select::make('vat_code_type')
                                            ->label('Aliquota IVA')
                                            ->options(
                                                collect(VatCodeType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                            )
                                            ->required()
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12)
                                    ->maxItems(10)
                                    ->defaultItems(0)
                                    ->addActionLabel('Aggiungi voce')
                                    ->deleteAction(
                                        fn ($action) => $action->label('Rimuovi voce')
                                    )
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(function (array $state): ?string {
                                        $vatLabel = isset($state['vat_code_type'])
                                            ? VatCodeType::tryFrom($state['vat_code_type'])?->getLabel() ?? ''
                                            : 'Senza aliquota';
                                        $name = $state['name'] ?? 'Nuova voce';
                                        return "$name ($vatLabel)";
                                    })
                                    ->columnSpan(12),
                            ])
                            ->columns(12),
                    ])
                    ->columnSpan(12),
            ]);
    }
}
