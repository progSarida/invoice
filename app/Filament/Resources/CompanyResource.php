<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\City;
use Filament\Tables;
use App\Enums\FundType;
use App\Models\Company;
use App\Models\DocType;
use Filament\Forms\Form;
use App\Enums\VatCodeType;
use Filament\Tables\Table;
use App\Enums\TaxRegimeType;
use App\Enums\VatEnforceType;
use App\Enums\LiquidationType;
use App\Enums\ShareholderType;
use App\Enums\WithholdingType;
use App\Livewire\DocTypeTable;
use App\Enums\PaymentReasonType;
use Filament\Resources\Resource;
use App\Models\SocialContribution;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use App\Filament\Resources\CompanyResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CompanyResource\RelationManagers;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    public static ?string $pluralModelLabel = 'Aziende';

    public static ?string $modelLabel = 'Azienda';

    protected static ?string $navigationIcon = 'gmdi-business-center-r';

    // protected static ?string $navigationGroup = 'Parametri';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(11),
                Forms\Components\Toggle::make('is_active')->label('Attiva')
                    ->columnSpan(1),
                Tabs::make('')
                    ->tabs([
                        Tabs\Tab::make('Informazioni')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Nome')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(6),
                                Forms\Components\TextInput::make('vat_number')->label('Partita Iva')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('tax_number')->label('Codice fiscale')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('city_code')
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
                                Forms\Components\TextInput::make('address')->label('Indirizzo')
                                    ->maxLength(255)
                                    ->required()
                                    ->columnSpan(6),
                                Forms\Components\Select::make('city')->label('Città')
                                    ->relationship(name: 'city', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('phone')->label('Telefono')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('email')->label('Email')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('pec')->label('Pec')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('fax')->label('Fax')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('Albo professionale')
                            ->schema([
                                Forms\Components\TextInput::make('register')->label('Albo professionale')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\Select::make('register_province_id')->label('Provincia Albo')
                                    ->relationship(name: 'registerProvince', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),
                                Forms\Components\TextInput::make('register_number')->label('Numero')
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                Forms\Components\DatePicker::make('register_date')->label('Data iscrizione')
                                    ->columnSpan(3),
                            ])
                            ->columns(12),
                        Tabs\Tab::make('REA')
                            ->schema([
                                Forms\Components\Select::make('rea_province_id')->label('Ufficio')
                                    ->relationship(name: 'reaProvince', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(5),
                                Placeholder::make('')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('rea_number')->label('Numero')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Placeholder::make('')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('nominal_capital')->label('Capitale sociale')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Placeholder::make('')
                                    ->label('')
                                    ->content('')
                                    ->columnSpan(6),
                                Forms\Components\Select::make('shareholders')
                                    ->label('Socio unico')
                                    ->options(ShareholderType::options())
                                    ->columnSpan(3),
                                Forms\Components\Select::make('liquidation')
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
                                Forms\Components\Repeater::make('socialContributions')
                                    ->label('Cassa previdenziale')
                                    ->relationship('socialContributions')
                                    ->schema([
                                        Forms\Components\Select::make('fund')
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
                                        Forms\Components\TextInput::make('rate')
                                            ->label('Aliquota cassa')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix('%')
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('taxable_perc')
                                            ->label('su')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix("% dell'imponibile")
                                            ->columnSpan(3),
                                        Forms\Components\Select::make('vat_code')
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
                                Forms\Components\Repeater::make('withholdings')
                                    ->label('Ritenuta')
                                    ->relationship('withholdings')
                                    ->schema([
                                        Forms\Components\Select::make('withholding_type')
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
                                        Forms\Components\TextInput::make('rate')
                                            ->label('Aliquota ritenuta')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix('%')
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('taxable_perc')
                                            ->label('su')
                                            ->required()
                                            ->maxLength(255)
                                            ->suffix("% dell'imponibile")
                                            ->columnSpan(3),
                                        Forms\Components\Select::make('payment_reason')
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
                        Tabs\Tab::make('Bollo automatico')
                            ->schema([
                                Section::make("Aggiungi automaticamente l'imposta di bollo nella fatture quando gli importi esenti IVA sono uguali o superiori al valore indicato.")
                                    ->relationship('stampDuty')
                                    ->extraAttributes(['style' => 'font-weight: normal;'])
                                    ->schema([
                                        Placeholder::make('')
                                            ->content("Fatture elettroniche")
                                            ->columnSpan(12)->extraAttributes(['style' => 'font-weight: bold; font-size: 1.25rem;']),
                                        Forms\Components\Toggle::make('active')
                                            ->label('Attiva')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (!$state) {
                                                    $set('value', '77.47');
                                                    $set('add_row', false);
                                                    $set('row_description', null);
                                                }
                                            })
                                            ->columnSpan(1),
                                        Placeholder::make('')->content('')->columnSpan(11),
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
                                            ->content("Addebita il costo del bollo al cliente aggiungendo una riga nella fattura elettronica")
                                            ->columnSpan(12)->extraAttributes(['style' => 'font-weight: bold; font-size: 1.25rem;']),
                                        Forms\Components\Toggle::make('add_row')
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
                                Forms\Components\CheckboxList::make('docTypes')
                                    ->label('')
                                    ->relationship('docTypes', 'id')
                                    ->options(fn () => DocType::flatOptions())
                                    ->bulkToggleable()
                                    ->columnSpan(12),
                            ])
                            ->columns(12),
                            Tabs\Tab::make('Sezionari')
                                ->schema([
                                    Forms\Components\Repeater::make('sectionals')
                                        ->label('')
                                        ->relationship('sectionals')
                                        ->schema([
                                            Forms\Components\Select::make('client_type')
                                                ->label('Tipo cliente')
                                                ->options(
                                                    collect(\App\Enums\ClientType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                                )
                                                ->required()
                                                ->columnSpan(3),
                                            Forms\Components\TextInput::make('description')
                                                ->label('Sezionario')
                                                ->maxLength(255)
                                                ->required()
                                                ->columnSpan(1),
                                            Forms\Components\Select::make('numeration_type')
                                                ->label('Tipo numerazione')
                                                ->options(
                                                    collect(\App\Enums\NumerationType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                                )
                                                ->required()
                                                ->columnSpan(3),
                                            Forms\Components\TextInput::make('progressive')
                                                ->label('Numero progressivo')
                                                ->maxLength(255)
                                                ->required()
                                                ->columnSpan(2),
                                            Forms\Components\CheckboxList::make('doc_type_ids')
                                                ->label('Tipi documento')
                                                ->options(function ($livewire, $record) {
                                                    $companyId = null;
                                                    if ($record && $record->company_id) {
                                                        $companyId = $record->company_id;
                                                    } elseif ($livewire->getRecord()) {
                                                        $companyId = $livewire->getRecord()->id;
                                                    }
                                                    return $companyId ? \App\Models\DocType::groupedOptions($companyId) : [];
                                                })
                                                ->relationship(
                                                    name: 'docTypes',
                                                    titleAttribute: null,
                                                    modifyQueryUsing: function ($query, $record, $livewire) {
                                                        $companyId = null;
                                                        if ($record && $record->company_id) {
                                                            $companyId = $record->company_id;
                                                        } elseif ($livewire->getRecord()) {
                                                            $companyId = $livewire->getRecord()->id;
                                                        }
                                                        if ($companyId) {
                                                            $query->whereIn('doc_types.id', function ($subQuery) use ($companyId) {
                                                                $subQuery->select('doc_type_id')
                                                                    ->from('company_docs')
                                                                    ->where('company_id', $companyId);
                                                            })->orderBy('doc_types.name', 'asc');
                                                        }
                                                    }
                                                )
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
                    ])
                    ->columnSpan(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vat_number')->label('Partita Iva')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')->label('Indirizzo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('Città')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefono')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label('Attiva'),
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
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Parametri';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }
}
