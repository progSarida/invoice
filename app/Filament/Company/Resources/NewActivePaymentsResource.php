<?php

namespace App\Filament\Company\Resources;

use App\Enums\ClientType;
use App\Enums\TaxType;
use App\Filament\Company\Resources\NewActivePaymentsResource\Pages;
use App\Filament\Company\Resources\NewActivePaymentsResource\RelationManagers;
use App\Models\AccrualType;
use App\Models\ActivePayments;
use App\Models\Invoice;
use App\Models\NewActivePayments;
use App\Models\Sectional;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class NewActivePaymentsResource extends Resource
{
    protected static ?string $model = ActivePayments::class;

    public static ?string $pluralModelLabel = 'Pagamenti';

    public static ?string $modelLabel = 'Pagamento';

    protected static ?string $navigationIcon = 'fluentui-payment-20-o';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    protected static ?int $navigationSort = 2;

    protected static ?int $navigationGroupSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->disabled(function ($record): bool { return $record !== null && !Auth::user()->isManager(); })
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Fattura')
                    ->placeholder('Seleziona una fattura...')
                    ->hintIcon('heroicon-o-information-circle', tooltip: "Digitare 'Tutte' nella ricerca per mostrare tutte fatture non pagate, oppure inserire direttamente numero e anno, oppure il nome del cliente.")
                    ->getSearchResultsUsing(function (string $search, $record) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));


                        // Query base con le stesse condizioni del relationship
                        $query = Invoice::query()
                            ->whereNotNull('flow')                                      // solo le fatture nuove
                            ->where('sdi_status', '!=', 'da_inviare')                   // solo le fatture inviate allo sdi
                            ->whereNull('parent_id')                                    // escludo le note di credito
                            ->with(['client', 'sectional'])
                            // ->where(function ($q) {
                            //     $q->whereHas('client', function ($clientQuery) {
                            //         $clientQuery->where('type', ClientType::PUBLIC);
                            //     })
                            //     ->whereColumn('total_payment', '<', 'no_vat_total')     // solo non pagate (verso pubb. amm.)
                            //     ->orWhere(function ($q2) {                              // o
                            //         $q2->whereHas('client', function ($clientQuery) {
                            //             $clientQuery->where('type', ClientType::PRIVATE);
                            //         })
                            //         ->whereColumn('total_payment', '<', 'total');       // solo no pagate (verso privati)
                            //     });
                            // });
                            ->where(function ($q) {
                                // Caso: Pubblica Amministrazione
                                $q->where(function ($publicQuery) {
                                    $publicQuery->whereHas('client', function ($clientQuery) {
                                        $clientQuery->where('type', ClientType::PUBLIC);
                                    })
                                    // Verifica se pagamenti + note di credito sono inferiori al totale imponibile
                                    ->whereRaw('(total_payment + total_notes) < no_vat_total');
                                })
                                // Caso: Privati
                                ->orWhere(function ($privateQuery) {
                                    $privateQuery->whereHas('client', function ($clientQuery) {
                                        $clientQuery->where('type', ClientType::PRIVATE);
                                    })
                                    // Verifica se pagamenti + note di credito sono inferiori al totale ivato
                                    ->whereRaw('(total_payment + total_notes) < total');
                                });
                            });

                        if(!str_contains(strtolower($search), "tutte")){                // filtro con la ricerca
                            // Cerco separatori (spazio, virgola, slash, trattino)
                            $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                            if (count($parts) >= 2) {
                                // Due o più valori: prendo i primi due e convertili a integer
                                $value1 = is_numeric($parts[0]) ? (int) $parts[0] : null;
                                $value2 = is_numeric($parts[1]) ? (int) $parts[1] : null;

                                if ($value1 !== null && $value2 !== null) {
                                    // Provo number/year o year/number (match esatto)
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
                                        });
                                    });
                                }
                            } elseif (count($parts) === 1) {
                                // Un solo valore: cerco SOLO match esatto in number o year
                                if (is_numeric($parts[0])) {
                                    $value = (int) $parts[0];
                                    $query->where(function ($q) use ($value) {
                                        $q->where('number', $value)
                                        ->orWhere('year', $value);
                                    });
                                }
                                else{
                                    // Se non è numerico, cerco match parziale in client.denomination o sectional.description
                                    $query->where(function ($q) use ($search) {
                                        $q->whereHas('client', function ($clientQ) use ($search) {
                                            $clientQ->where('denomination', 'like', "%{$search}%");
                                        });
                                    });
                                }
                            }
                        }

                        return $query
                            ->orderBy('invoice_date', 'desc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $cliente = $record->client?->denomination ?? 'Cliente sconosciuto';
                                $sectional = $record->sectional?->description ?? 'N/A';
                                $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                                $year = $record->year ?? '????';
                                $label = "{$cliente} - {$number}/{$sectional}/{$year}";

                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $record = Invoice::find($value);

                        if (!$record) {
                            return null;
                        }

                        $cliente = $record->client?->denomination ?? 'Cliente sconosciuto';
                        $sectional = $record->sectional?->description ?? 'N/A';
                        $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                        $year = $record->year ?? '????';

                        return "{$cliente} - {$number}/{$sectional}/{$year}";
                    })
                    ->afterStateUpdated(function(Set $set, $state) {
                        if ($state) {
                            $invoice = Invoice::find($state);
                            $amount = $invoice->client->type == ClientType::PUBLIC
                                        ? $invoice->no_vat_total - ($invoice->total_payment + $invoice->total_notes)
                                        : $invoice->total - ($invoice->total_payment + $invoice->total_notes);
                            if ($invoice) {
                                $set('bank_account_id', $invoice->bank_account_id);
                                $set('amount', number_format($amount, 2, ",", "."));
                            }
                        }
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable()
                    ->dehydrated()
                    ->live()
                    ->preload()
                    ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->live(onBlur: true)
                    // ->debounce(2000)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(fn ($get) => !$get('invoice_id') || $get('validated'))
                    // ->afterStateUpdated(function ($state, Get $get, $component) {
                    //     if (!$get('invoice_id')) return;
                    //     $invoice = Invoice::find($get('invoice_id'));
                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                    //     }
                    //     else {
                    //         $amount = $state ?? 0;
                    //     }
                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                    //     $number = str_replace(',', '.', $clean);
                    //     $float = floatval($number);

                    //     $newTotalPayment = $amount + $invoice->total_payment;
                    //     $compare = $invoice->client?->type?->value == 'public'
                    //                 ? $invoice->no_vat_total
                    //                 : $invoice->total;
                    //     $formatted = number_format($float, 2, ',', '.');
                    //     $component->state($formatted);
                    //     if($state != $compare){
                    //         Notification::make()
                    //             ->title("Attenzione! L'importo del pagamento è diverso dal totale della fattura " . $invoice->getNewInvoiceNumber())
                    //             ->danger()
                    //             ->duration(5000)
                    //             // ->persistent()
                    //             ->send();
                    //     }
                    //     if($newTotalPayment > ($compare - $invoice->total_notes)){
                    //         Notification::make()
                    //             ->title('Attenzione! Con questo inserimento il totale dei pagamenti della fattura ' . $invoice->getNewInvoiceNumber() . ' eccederebbe il dovuto.')
                    //             ->danger()
                    //             ->duration(6000)
                    //             // ->persistent()
                    //             ->send();
                    //     }
                    // })
                    ->afterStateUpdated(function ($state, Get $get, $component) {
                        if (!$get('invoice_id')) return;
                        $invoice = Invoice::find($get('invoice_id'));
                        $amount = CurrencyService::parseNumber($state);
                        $formatted = number_format($amount, 2, ',', '.');
                        $component->state($formatted);
                        $newTotalPayment = $amount + $invoice->total_payment;
                        $compare = $invoice->client?->type?->value == 'public'
                            ? $invoice->no_vat_total
                            : $invoice->total;
                        if($amount != $compare){
                            Notification::make()
                                ->title("Attenzione! L'importo del pagamento è diverso dal totale della fattura " . $invoice->getNewInvoiceNumber())
                                ->danger()
                                ->duration(5000)
                                ->send();
                        }
                        if($newTotalPayment > ($compare - $invoice->total_notes)){
                            Notification::make()
                                ->title('Attenzione! Con questo inserimento il totale dei pagamenti della fattura ' . $invoice->getNewInvoiceNumber() . ' eccederebbe il dovuto.')
                                ->danger()
                                ->duration(6000)
                                ->send();
                        }
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    // ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled(fn ($get) => $get('validated'))
                    ->dehydrated()
                    ->reactive()
                    ->required()
                    ->debounce(500)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $invoice = Invoice::find($get('invoice_id'));
                        $paymentDate = $get('payment_date');

                        if ($paymentDate && $invoice && ($paymentDate < $invoice->invoice_date)) {
                            Notification::make('date')
                                ->title('Attenzione! La data del pagamento è inferiore alla data della fattura.')
                                ->danger()
                                ->duration(6000)
                                // ->persistent()
                                ->send();
                        }

                        if ($paymentDate && $paymentDate > today()) {
                            Notification::make('today')
                                ->title('Attenzione! La data del pagamento è successiva alla data di oggi.')
                                ->warning()
                                ->duration(6000)
                                // ->persistent()
                                ->send();
                        }
                    })
                    ->date()
                    ->columnSpan(2),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(1),
                Toggle::make('validated')
                    ->label('Validato')
                    ->live()
                    ->default(false)
                    ->afterStateUpdated(function (Set $set, bool $state) {
                        if ($state) {
                            $set('validation_date', now()->format('Y-m-d'));
                            $set('validation_user_id', Auth::id());
                        } else {
                            // Per "annullare" la validazione quando il toggle viene disattivato
                            $set('validation_date', null);
                            $set('validation_user_id', null);
                        }
                    })
                    ->columnSpan(2),
                Forms\Components\Select::make('bank_account_id')->label('Conto')
                    ->relationship(
                        name: 'bankAccount',
                        modifyQueryUsing: fn (Builder $query) =>
                        $query->where('company_id',Filament::getTenant()->id)->orderBy('position', 'asc')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name} - $record->iban"
                    )
                    ->searchable()
                    ->required()
                    ->columnSpan(5)
                    ->preload(),
                // Forms\Components\Placeholder::make('')
                //     ->columnSpan(7),
                Forms\Components\Textarea::make('description')->label('Descrizione')
                    ->columnSpan(7),
                Forms\Components\Textarea::make('note')->label('Note')
                    ->columnSpanFull(),
                Section::make('Dati registrazione/validazione')
                        // ->collapsible()
                        ->columns(12)
                        ->collapsed()
                        ->label('')
                        ->visible(fn ($get) => !is_null($get('registration_date')))
                        ->schema([
                            DatePicker::make('registration_date')
                                ->label('Data registrazione')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->disabled()
                                ->date()
                                ->columnSpan(2),
                            Forms\Components\Select::make('registered_by_user_id')
                                ->label('Registrato da')
                                ->relationship('registrationUser', 'name')
                                ->disabled()
                                ->columnSpan(3),
                            DatePicker::make('validation_date')
                                ->label('Data validazione')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->disabled()
                                ->visible(fn ($get) => $get('validated'))
                                ->columnSpan(2),
                            Forms\Components\Select::make('validated_by_user_id')
                                ->label('Validato da')
                                ->relationship('validationUser', 'name')
                                ->disabled()
                                ->visible(fn ($get) => $get('validated'))
                                ->columnSpan(3),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(ActivePayments::newActivePayments())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_formatted')
                    ->label('Fattura')
                    ->getStateUsing(function ($record) {
                        $invoice = $record->invoice;
                        if (!$invoice) {
                            return 'Nessuna fattura';
                        }
                        $number = "";
                        $sectional = Sectional::find($invoice->sectional_id)->description;
                        for($i=strlen($invoice->number);$i<3;$i++)
                        {
                            $number.= "0";
                        }
                        $number = $number.$invoice->number;
                        return $number." / ".$sectional." / ".$invoice->year;

                        // $number = str_pad($invoice->number, 3, '0', STR_PAD_LEFT); // es: 007
                        // return "{$number}/{$invoice->section}/{$invoice->year}";
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Importo')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('client')->label('Cliente')
                    // ->numeric()
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        $client = $record->invoice->client;
                        return $client->denomination;
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data pagamento')
                    ->getStateUsing(function ($record) {
                        return $record->payment_date
                            ? Carbon::parse($record->payment_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Data registrazione')
                    ->getStateUsing(function ($record) {
                        return $record->registration_date
                            ? Carbon::parse($record->registration_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('registrationUser.name')
                    ->label('Registrato da')
                    ->getStateUsing(fn ($record) => optional($record->registrationUser)->name ?? 'Nessun utente')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('validated')
                    ->label('Validato')
                    ->sortable()
                    ->afterStateUpdated(function (\App\Models\ActivePayments $record, bool $state) {
                        if ($state) {
                            $record->validation_date = now();
                            $record->validation_user_id = Auth::user()->id;
                        } else {
                            $record->validation_date = null;
                            $record->validation_user_id = null;
                        }

                        $record->save();
                    }),
            ])
            ->filters([
                SelectFilter::make('invoice_client_type')
                    ->label('Destinatario')
                    ->options(ClientType::class)
                    ->attribute(null)
                    // ->columnSpan(2)
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->whereHas('invoice.client', function ($q) use ($value) {
                                $q->where('type', $value);
                            });
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('invoice_client_id')
                    ->label('Cliente')
                    ->attribute(null)
                    // ->columnSpan(3)
                    ->options(function () {
                        $tenant = Filament::getTenant();

                        return \App\Models\Client::query()
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->get()
                            ->mapWithKeys(function ($client) {
                                // $label = strtoupper($client->subtype->getLabel()) . ' - ' . $client->denomination;
                                $label = $client->denomination;
                                return [$client->id => $label];
                            })
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->whereHas('invoice.client', function ($q) use ($value) {
                                $q->where('id', $value);
                            });
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('invoice_tax_type')
                    ->label('Entrata')
                    ->options(TaxType::class)
                    ->attribute(null)
                    // ->columnSpan(2)
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        // dd($data);
                        $values = $data['values'] ?? [];
                        if (!empty($values)) {
                            return $query->whereHas('invoice', function ($q) use ($values) {
                                $q->whereIn('tax_type', $values);
                            });
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
                                    Filter::make('invoice_number')
                    ->form([
                TextInput::make('number')
                            ->label('Numero Fattura'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['number'])) {
                            return $query->whereHas('invoice', function ($q) use ($data) {
                                $q->where('number', $data['number']);
                            });
                        }
                        return $query;
                    }),
                SelectFilter::make('contract_accrual_types')
                    ->label('Gestioni')
                    ->options(function () {
                        return AccrualType::query()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->attribute(null)
                    // ->columnSpan(2)
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? [];
                        if (!empty($values)) {
                            return $query->whereHas('invoice.contract', function ($q) use ($values) {
                                foreach ($values as $value) {
                                    $q->whereJsonContains('accrual_types', $value);
                                }
                            });
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('validated')
                    ->label('Validati')
                    ->options([
                        'si' => 'Sì',
                        'no' => 'No',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        $sql = 'total - (total_payment + total_notes)';
                        return $query->when($data['value'] === 'si', fn ($q) => $q->where('validated', true))
                                    ->when($data['value'] === 'no', fn ($q) => $q->where('validated', false));
                    })
                    ->preload(),
                Filter::make('payment_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('payment_from_date')
                            ->label('Pagamento da')
                            ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $set('payment_to_date', $state);
                                }
                            })
                            ->columnSpan(1),
                        DatePicker::make('payment_to_date')
                            ->label('Pagamento a')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['payment_from_date'])) {
                            $query->whereDate('payment_date', '>=', $data['payment_from_date']);
                        }
                        if (! empty($data['payment_to_date'])) {
                            $query->whereDate('payment_date', '<=', $data['payment_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['payment_from_date'] && $data['payment_to_date']) {
                            return "Pagamento dal " . Carbon::parse($data['payment_from_date'])->format('d/m/Y') . " al " . Carbon::parse($data['payment_to_date'])->format('d/m/Y');
                        }
                        if ($data['payment_from_date']) {
                            return "Pagamento dal " . Carbon::parse($data['payment_from_date'])->format('d/m/Y');
                        }
                        if ($data['payment_to_date']) {
                            return "Pagamento al " . Carbon::parse($data['payment_to_date'])->format('d/m/Y');
                        }
                        return null;
                    })
                    ->columnSpan(2),
                SelectFilter::make('invoice_year')
                    ->label('Anno Fattura')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = Filament::getTenant();
                        return Invoice::query()
                            ->select('year')
                            ->distinct()
                            ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderBy('year')
                            ->pluck('year', 'year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->whereHas('invoice', function ($q) use ($value) {
                                $q->where('year', $value);
                            });
                        }
                        return $query;
                    })
                    ->default(now()->year),
                SelectFilter::make('invoice_budget_year')
                    ->label('Anno Bilancio')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = Filament::getTenant();
                        return Invoice::query()
                            ->select('budget_year')
                            ->distinct()
                            ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('budget_year')
                            ->pluck('budget_year', 'budget_year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->whereHas('invoice', function ($q) use ($value) {
                                $q->where('budget_year', $value);
                            });
                        }
                        return $query;
                    }),
                SelectFilter::make('invoice_accrual_year')
                    ->label('Anno Competenza')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = Filament::getTenant();
                        return Invoice::query()
                            ->select('accrual_year')
                            ->distinct()
                            ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('accrual_year')
                            ->pluck('accrual_year', 'accrual_year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->whereHas('invoice', function ($q) use ($value) {
                                $q->where('accrual_year', $value);
                            });
                        }
                        return $query;
                    }),
            // ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(8)
            ])->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()->isManager()),
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
            'index' => Pages\ListNewActivePayments::route('/'),
            'create' => Pages\CreateNewActivePayments::route('/create'),
            'edit' => Pages\EditNewActivePayments::route('/{record}/edit'),
            'view' => Pages\ViewNewActivePayments::route('/{record}'),
        ];
    }
}
