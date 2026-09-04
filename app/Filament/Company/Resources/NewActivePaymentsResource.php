<?php

namespace App\Filament\Company\Resources;

use App\Enums\ClientType;
use App\Enums\PaymentType;
use App\Enums\TaxType;
use App\Filament\Company\Resources\NewActivePaymentsResource\Pages;
use App\Filament\Company\Resources\NewActivePaymentsResource\RelationManagers;
use App\Models\AccrualType;
use App\Models\ActivePayments;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\NewActivePayments;
use App\Models\Sectional;
use App\Policies\ActivePaymentsPolicy;
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
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
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

    public static ?string $pluralModelLabel = 'Pagamenti attivi';

    public static ?string $modelLabel = 'Pagamento attivo';

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
                    // ->hintIcon('heroicon-o-information-circle', tooltip: "Digitare 'Tutte' nella ricerca per mostrare tutte fatture non pagate, oppure inserire direttamente numero e anno, oppure il nome del cliente.")
                    ->hintIcon('heroicon-o-information-circle', tooltip: "Ricerca su cliente, numero o anno fattura; aggiungere alla ricerca 'pagate' per ricercare tra le fatture pagate. Digitare 'tutte' per mostrare tutte le fatture non pagate.")
                    ->getSearchResultsUsing(function (string $search, $record) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));


                        // Query base con le stesse condizioni del relationship
                        $query = Invoice::query()
                            ->whereNotNull('flow')                                      // solo le fatture nuove
                            ->where('sdi_status', '!=', 'da_inviare')                   // solo le fatture inviate allo sdi
                            ->whereNull('parent_id')                                    // escludo le note di credito
                            ->with(['client', 'sectional']);
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
                            

                        if(str_contains(strtolower($search), "pagate")){
                            $query->where(function ($q) {
                                // Caso: totale della fattura comprensivo di IVA
                                $q->where(function ($vatQuery) {
                                    $vatQuery->where('is_total_with_vat', true)
                                        // Verifica se pagamenti + note di credito coprono il totale ivato
                                        ->whereRaw('(total_payment + total_notes) >= total');
                                })
                                // Caso: totale della fattura al netto dell'IVA
                                ->orWhere(function ($noVatQuery) {
                                    $noVatQuery->where('is_total_with_vat', false)
                                        // Verifica se pagamenti + note di credito coprono il totale imponibile
                                        ->whereRaw('(total_payment + total_notes) >= no_vat_total');
                                });
                            });
                        } else {
                            $query->where(function ($q) {
                                // Caso: totale della fattura comprensivo di IVA
                                $q->where(function ($vatQuery) {
                                    $vatQuery->where('is_total_with_vat', true)
                                        // Verifica se pagamenti + note di credito sono inferiori al totale ivato
                                        ->whereRaw('(total_payment + total_notes) < total');
                                })
                                // Caso: totale della fattura al netto dell'IVA
                                ->orWhere(function ($noVatQuery) {
                                    $noVatQuery->where('is_total_with_vat', false)
                                        // Verifica se pagamenti + note di credito sono inferiori al totale imponibile
                                        ->whereRaw('(total_payment + total_notes) < no_vat_total');
                                });
                            });
                        }

                        if(!str_contains(strtolower($search), "tutte")){                // filtro con la ricerca
                            // Cerco separatori (spazio, virgola, slash, trattino)
                            $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                            // rimuovo 'pagat*'
                            $parts = array_filter($parts, function($word) {
                                // Ritorna false se la parola è "pagate", "pagato", "pagati", "pagata"
                                return !preg_match('/^pagat[eoi]a?$/i', trim($word));
                            });

                            // resetto gli indico dell'array
                            $parts = array_values($parts);

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

                            if($invoice->isPaid()){
                                Notification::make()
                                    ->title('Attenzione! La fattura selezionata è già stata pagata')
                                    ->danger()
                                    ->duration(6000)
                                    ->send();
                                return;
                            }

                            // VECCHIO CALCOLO
                            // $notRound = BankAccount::find($invoice?->bank_account_id)?->name != 'Giroconto';
                            // $amount = ($invoice->client?->type == ClientType::PUBLIC && $notRound)
                            //             ? $invoice->no_vat_total - ($invoice->total_payment + $invoice->total_notes)
                            //             : $invoice->total - ($invoice->total_payment + $invoice->total_notes);

                            // NUOVO CALCOLO USANDO is_total_with_vat
                            $newNoVatTotal = $invoice->no_vat_total - $invoice->creditNotes?->sum('no_vat_total');
                            $newVat = $invoice->vat - $invoice->creditNotes?->sum('vat');
                            $temp = $newNoVatTotal- $invoice->total_payment;
                            $amount = $invoice->is_total_with_vat ? $temp + $newVat : $temp;

                            if ($invoice) {
                                $set('bank_account_id', $invoice->bank_account_id);
                                $set('payment_type', $invoice->payment_type?->value);           // metodo di pagamento ereditato dalla fattura
                                $set('amount', number_format($amount, 2, ",", "."));
                            }
                        }
                    })
                    ->required()
                    ->validationMessages([
                        'required' => 'La fattura è obbligatoria.',
                    ])
                    ->disabled(fn ($get, $livewire) => $livewire instanceof ViewRecord || $get('validated'))
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->validationMessages([
                        'required' => 'L\'importo è obbligatorio.',
                    ])
                    ->live(onBlur: true)
                    // ->debounce(2000)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(fn ($get, $livewire) => $livewire instanceof ViewRecord || !$get('invoice_id') || $get('validated'))
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

                        // VECCHIO CALCOLO CONTROLLO
                        // $notRound = BankAccount::find($invoice?->bank_account_id)?->name != 'Giroconto';
                        // $compare = ($invoice->client?->type?->value == 'public' && $notRound)
                        //     ? $invoice->no_vat_total
                        //     : $invoice->total;

                        // NUOVO CALCOLO CONTROLLO USANDO is_total_with_vat
                        $newNoVatTotal = $invoice->no_vat_total - $invoice->creditNotes?->sum('no_vat_total');
                        $newVat = $invoice->vat - $invoice->creditNotes?->sum('vat');
                        $temp = $newNoVatTotal- $invoice->total_payment;
                        $compare = $invoice->is_total_with_vat ? $temp + $newVat : $temp;
                        $comparePayment = $invoice->is_total_with_vat ? $newNoVatTotal + $newVat : $newNoVatTotal;

                        // if($newTotalPayment > ($compare - $invoice->total_notes)){
                        if($newTotalPayment > $comparePayment){
                            Notification::make()
                                ->title('Attenzione! Con questo inserimento il totale dei pagamenti della fattura ' . $invoice->getNewInvoiceNumber() . ' eccederebbe il dovuto.')
                                ->danger()
                                ->duration(6000)
                                ->send();
                        }
                        else if($amount != $compare){
                            Notification::make()
                                ->title("Attenzione! L'importo del pagamento è diverso dal residuo del dovuto della fattura " . $invoice->getNewInvoiceNumber())
                                ->danger()
                                ->duration(5000)
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
                    ->disabled(fn ($get, $livewire) => $livewire instanceof ViewRecord || $get('validated'))
                    ->reactive()
                    ->required()
                    ->validationMessages([
                        'required' => 'La data è obbligatoria.',
                    ])
                    ->debounce(500)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if (!$state) {
                            return;
                        }

                        $invoice = Invoice::find($get('invoice_id'));
                        $paymentDate = $get('payment_date');

                        $currentMonth = now()->month;
                        $date = \Carbon\Carbon::parse($state);
                        $selectedYear = \Carbon\Carbon::parse($state)->year;
                        $currentYear = now()->year;

                        if ($currentMonth !== 1 && $date->year !== $currentYear) {
                            $corrected = $date->copy()->setYear($currentYear);
                            $set('payment_date', $corrected->format('Y-m-d'));

                            Notification::make()
                                ->title('Anno corretto automaticamente')
                                ->body("Hai inserito una data del {$selectedYear}, ma l'anno corrente è il {$currentYear}.")
                                ->warning()
                                ->send();
                        }

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
                    ->visible(fn ($record) => $record?->amount !== null && auth()->user()->can('update', $record))
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
                    ->disabled(fn ($get, $livewire) => $livewire instanceof ViewRecord || $get('validated'))
                    ->searchable()
                    ->required()
                    ->validationMessages([
                        'required' => 'Il conto è obbligatorio.',
                    ])
                    ->columnSpan(6)
                    ->preload(),
                Forms\Components\Select::make('payment_type')
                    ->label('Metodo di pagamento')
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->value => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    ->disabled(fn ($get, $livewire) => $livewire instanceof ViewRecord || $get('validated'))
                    ->columnSpan(6),
                // Forms\Components\Placeholder::make('')
                //     ->columnSpan(7),
                Forms\Components\Textarea::make('description')->label('Descrizione')
                    ->disabled(fn ($livewire) => $livewire instanceof ViewRecord)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('note')->label('Note')
                    ->disabled(fn ($livewire) => $livewire instanceof ViewRecord)
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
                Tables\Columns\TextColumn::make('id')->label('🔍 Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_formatted')
                    ->label('🔍 Fattura')
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('invoice', function (Builder $query) use ($search) {
                            $query->whereHas('sectional', function (Builder $query) use ($search) {
                                $query->where('description', 'like', "%{$search}%");
                            })
                            ->orWhere('number', 'like', "%{$search}%")
                            ->orWhere('year', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->leftJoin('invoices', 'invoices.id', '=', 'active_payments.invoice_id')
                            ->orderBy('invoices.year', $direction)
                            ->orderBy('invoices.number', $direction);
                    }),
                Tables\Columns\TextColumn::make('amount')->label('🔍 Importo')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('client')->label('🔍 Cliente')
                    // ->numeric()
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('invoice.client', function (Builder $query) use ($search) {
                            $query->where('denomination', 'like', "%{$search}%");
                        });
                    })
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
                // Tables\Columns\ToggleColumn::make('validated')
                //     ->label('Validato')
                //     ->sortable()
                //     ->disabled(function (\App\Models\ActivePayments $record) {
                //         return !auth()->user()->can('update', $record);
                //     })
                //     ->afterStateUpdated(function (\App\Models\ActivePayments $record, bool $state) {
                //         if ($state) {
                //             $record->validation_date = now();
                //             $record->validation_user_id = Auth::user()->id;
                //         } else {
                //             $record->validation_date = null;
                //             $record->validation_user_id = null;
                //         }

                //         $record->save();
                //     }),
                
                Tables\Columns\IconColumn::make('validated')
                    ->label('Validato')
                    ->boolean()
                    ->sortable()
                    ->tooltip(fn (\App\Models\ActivePayments $record) => !auth()->user()->can('update', $record)
                        ? '' : ($record->validated
                        ? 'Annulla validazione pagamento.'
                        : 'Valida pagamento.')
                    )
                    ->disabled(function (\App\Models\ActivePayments $record) {
                        // Disabilita se l'utente NON può aggiornare il record
                        return !auth()->user()->can('update', $record);
                    })
                    ->action(
                        Tables\Actions\Action::make('toggleValidated')
                            ->requiresConfirmation()
                            ->disabled(function (\App\Models\ActivePayments $record) {
                                return !auth()->user()->can('update', $record);
                            })
                            ->modalHeading(fn (\App\Models\ActivePayments $record) => $record->validated
                                ? 'Rimuovere validazione?'
                                : 'Confermare validazione?'
                            )
                            ->modalDescription(fn (\App\Models\ActivePayments $record) => $record->validated
                                ? 'Il pagamento verrà marcato come non validato.'
                                : 'Il pagamento verrà marcato come validato.'
                            )
                            ->modalSubmitActionLabel('Conferma')
                            ->action(function (\App\Models\ActivePayments $record) {
                                $newState = ! $record->validated;
                                if ($newState) {
                                    $record->validation_date = now();
                                    $record->validation_user_id = Auth::user()->id;
                                } else {
                                    $record->validation_date = null;
                                    $record->validation_user_id = null;
                                }
                                $record->validated = $newState;
                                $record->save();
                            })
                    ),

                // Tables\Columns\ToggleColumn::make('validated')
                //     ->label('Validato')
                //     ->sortable()
                //     ->tooltip(fn (\App\Models\ActivePayments $record) => !auth()->user()->can('update', $record)
                //         ? '' : ($record->validated
                //         ? 'Annulla validazione pagamento.'
                //         : 'Valida pagamento.')
                //     )
                //     ->disabled(function (\App\Models\ActivePayments $record) {
                //         // Disabilita se l'utente NON può aggiornare il record
                //         return !auth()->user()->can('update', $record);
                //     })
                //     ->afterStateUpdated(function (\App\Models\ActivePayments $record, bool $state) {
                //         if ($state) {
                //             $record->validation_date = now();
                //             $record->validation_user_id = Auth::user()->id;
                //         } else {
                //             $record->validation_date = null;
                //             $record->validation_user_id = null;
                //         }

                //         $record->save();
                //     }),
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
                    ->preload()
                    ->columnSpan(['default' => 'full', 'lg' => 3]),
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
                    ->preload()
                    ->columnSpan(['default' => 'full', 'lg' => 3]),
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
                    ->preload()
                    ->columnSpan(['default' => 'full', 'lg' => 3]),

                SelectFilter::make('bank_account_id')
                    ->label('Conto')
                    ->relationship(
                        name: 'bankAccount',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) =>
                        $query->where('company_id',Filament::getTenant()->id)->orderBy('position', 'asc')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name} {$record->iban}"
                    )
                    ->columnSpan(['default' => 'full', 'lg' => 3]),

                SelectFilter::make('payment_type')
                    ->label('Metodo di pagamento')
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->value => $type->getLabel()                            // sui pagamenti attivi è salvato il value
                            ])
                            ->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }
                        // colonna qualificata: ordinando per fattura la query fa un join con invoices, che ha lo stesso campo
                        return $query->where('active_payments.payment_type', $data['value']);
                    })
                    ->columnSpan(['default' => 'full', 'lg' => 3]),

                Filter::make('invoice_number')
                    ->form([
                        TextInput::make('number')
                            ->label('Numero Documento'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['number'])) {
                            return $query->whereHas('invoice', function ($q) use ($data) {
                                $q->where('number', $data['number']);
                            });
                        }
                        return $query;
                    })
                    ->columnSpan(['default' => 'full', 'lg' => 3]),
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
                    ->preload()
                    ->columnSpan(['default' => 'full', 'lg' => 5]),
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
                    ->preload()
                    ->columnSpan(1),
                SelectFilter::make('invoice_year')
                    ->label('Anno Documento')
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
                    ->default(now()->year)
                    ->columnSpan(['default' => 'full', 'lg' => 2]),
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
                    })
                    ->columnSpan(['default' => 'full', 'lg' => 2]),
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
                    })
                    ->columnSpan(['default' => 'full', 'lg' => 2]),
                Filter::make('payment_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('payment_from_date')
                            ->label('Pagamento da')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $set('payment_to_date', $state);
                                }
                            })
                            ->columnSpan(1),
                        DatePicker::make('payment_to_date')
                            ->label('Pagamento a')
                            ->extraInputAttributes(['class' => 'text-center'])
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
                    ->columnSpan(['default' => 'full', 'lg' => 6]),
            // ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(8)
            ])->filtersFormColumns(6)->filtersFormWidth(MaxWidth::ThreeExtraLarge)
            ->persistFiltersInSession()
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
