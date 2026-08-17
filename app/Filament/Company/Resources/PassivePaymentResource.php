<?php

namespace App\Filament\Company\Resources;

use App\Enums\PaymentType;
use App\Models\Supplier;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\PassivePayment;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\PassivePaymentResource\Pages;
// use App\Filament\Company\Resources\PassivePaymentResource\RelationManagers;
use App\Models\DocType;
use App\Models\PassiveInvoice;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class PassivePaymentResource extends Resource
{
    protected static ?string $model = PassivePayment::class;

    public static ?string $pluralModelLabel = 'Pagamenti';

    public static ?string $modelLabel = 'Pagamento';

    protected static ?string $navigationIcon = 'fluentui-payment-20-o';

    protected static ?string $navigationGroup = 'Fatture passive';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            // ->disabled(function ($record): bool { return $record !== null && !Auth::user()->isManager(); })
            ->schema([
                Forms\Components\Select::make('passive_invoice_id')
                    ->label('Fattura validata da pagare')
                    ->relationship(name: 'passiveInvoice', titleAttribute: 'id')
                    ->searchable()
                    ->hintIcon('heroicon-o-information-circle', tooltip: "Ricerca su fornitore, numero, importo o data fattura; aggiungere alla ricerca 'pagate' per ricercare tra le fatture pagate. Digitare 'tutte' per mostrare tutte le fatture non pagate.")
                    ->getSearchResultsUsing(function (string $search, ?Model $record) {
                        $search = trim(preg_replace('/\s+/', ' ', $search));
                        $terms = explode(' ', $search);
                        $filterGroup = function ($q) {
                            $q->whereHas('docGroup', fn ($qGroup) => $qGroup->where('name', 'Autofatture'));
                        };

                        if(str_contains(strtolower($search), "pagate")){
                            $terms = array_filter($terms, function($term) {
                                $termLower = strtolower(trim($term));
                                // Ritorna true solo se la parola NON è "pagate" (o "pagato", "pagati", ecc.)
                                return !in_array($termLower, ['pagate', 'pagate', 'pagato', 'pagati', 'pagata']);
                            });
                            $terms = array_values($terms);
                            $query = PassiveInvoice::query()
                                ->where(function ($query) use ($record) {
                                    // Continua a mostrare le fatture non pagate...
                                    $query->whereRaw('(total_payment + total_note) >= total')
                                    // ...OPPURE la fattura che è già associata a questo pagamento (se siamo in edit)
                                    ->when($record, fn($q) => $q->orWhere('id', $record->passive_invoice_id));
                                });
                        } else {
                            $query = PassiveInvoice::query()
                                ->where(function ($query) use ($record) {
                                    // Continua a mostrare le fatture non pagate...
                                    $query->whereRaw('(total_payment + total_note) < total')
                                    // ...OPPURE la fattura che è già associata a questo pagamento (se siamo in edit)
                                    ->when($record, fn($q) => $q->orWhere('id', $record->passive_invoice_id));
                                });
                        }

                        if(str_contains(strtolower($search), "tutte")){
                            $list = $query->where('doc_type', '!=', 'TD04')
                                ->whereDoesntHave('docType', $filterGroup)
                                ->whereHas('piValidation', fn($sub) => $sub->where('pi_validation_status', 'ok'))
                                ->with(['supplier'])
                                ->limit(50)
                                // ->toSql();dd($list);
                                ->get()
                                ->mapWithKeys(fn (PassiveInvoice $i) => [$i->id => static::getPassiveInvoiceLabel($i)]) // Usa una funzione helper
                                ->toArray();
                        } else {
                            $list = $query->where('doc_type', '!=', 'TD04')
                                ->whereDoesntHave('docType', $filterGroup)
                                ->whereHas('piValidation', fn($sub) => $sub->where('pi_validation_status', 'ok'))
                                ->where(function ($mainQuery) use ($terms) {
                                    foreach ($terms as $term) {
                                        $mainQuery->where(function ($subQuery) use ($term) {
                                            $subQuery->whereHas('supplier', fn($s) => $s->where('denomination', 'like', "%{$term}%"))
                                                ->orWhere('number', 'like', "%{$term}%")
                                                ->orWhere('total', 'like', "%{$term}%")
                                                ->orWhere('invoice_date', 'like', "%{$term}%");
                                        });
                                    }
                                })
                                ->with(['supplier'])
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (PassiveInvoice $i) => [$i->id => static::getPassiveInvoiceLabel($i)]) // Usa una funzione helper
                                ->toArray();
                        }
                        return $list;
                    })
                    // FONDAMENTALE: Questo risolve il problema della label mancante all'avvio
                    ->getOptionLabelUsing(function ($value) {
                        $invoice = PassiveInvoice::with('supplier')->find($value);
                        return $invoice ? static::getPassiveInvoiceLabel($invoice) : null;
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        $passiveInvoice = PassiveInvoice::find($state);
                        if ($passiveInvoice) {
                            $amount = $passiveInvoice->total - ($passiveInvoice->total_payment + $passiveInvoice->total_note);
                            if($amount <= 0)
                                Notification::make()
                                    ->title('Attenzione! La fattura selezionata è già stata pagata')
                                    ->danger()
                                    ->duration(6000)
                                    ->send();
                            $set('amount', number_format($amount, 2, ",", "."));
                            $set('bank', $passiveInvoice->bank);
                            $set('iban', $passiveInvoice->iban);
                            $set('payment_type', $passiveInvoice->payment_type);
                        }
                    })
                    ->live()
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->columnSpan(6),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->disabled(fn ($get) => !$get('passive_invoice_id') || $get('validated'))
                    ->live(onBlur: true)
                    ->debounce(2000)
                    ->extraInputAttributes(['class' => 'text-right'])
                    // ->afterStateUpdated(function ($state, $component) {
                    //     if(str_contains($state, ',')){                                  // Se contiene una virgola
                    //         $amount = str_replace(',', '.', str_replace('.', '', $state));                                          // rimuovo i punti e sostituisco la virgola
                    //     }
                    //     else {
                    //         $amount = $state ?? 0;
                    //     }
                    //     $clean = preg_replace('/[^\d,\.-]/', '', $amount);
                    //     $number = str_replace(',', '.', $clean);
                    //     $float = floatval($number);
                    //     $formatted = number_format($float, 2, ',', '.');
                    //     $component->state($formatted);
                    // })
                    ->afterStateUpdated(function ($state, $component) {
                        $float = CurrencyService::parseNumber($state);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    // ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                    // ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->required()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled(fn ($get) => $get('validated'))
                    ->date()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (!$state) {
                            return;
                        }

                        $currentMonth = now()->month;
                        $date = \Carbon\Carbon::parse($state);
                        $selectedYear = \Carbon\Carbon::parse($state)->year;
                        $currentYear = now()->year;

                        if ($currentMonth !== 1 && $date->year !== $currentYear) {
                            $corrected = $date->copy()->setYear($currentYear);
                            $set('invoice_date', $corrected->format('Y-m-d'));

                            Notification::make()
                                ->title('Anno corretto automaticamente')
                                ->body("Hai inserito una data del {$selectedYear}, ma l'anno corrente è il {$currentYear}.")
                                ->warning()
                                ->send();
                        }
                    })
                    ->columnSpan(2),
                // Forms\Components\Placeholder::make('')
                //     ->content('')
                //     ->columnSpan(1),
                Forms\Components\Toggle::make('validated')
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
                    ->visible(fn($record) => $record)
                    ->columnSpan(2),
                //
                Forms\Components\TextInput::make('bank')
                    ->label('Banca da accreditare')
                    ->columnSpan(8),
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN da accreditare')
                    ->columnSpan(3),
                Forms\Components\Select::make('bank_account_id')->label('Conto di debito')
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
                Forms\Components\Select::make('payment_type')
                    ->label('Metodo di pagamento')
                    ->columnSpan(5)
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->getCode() => $type->getLabel()
                            ])
                            ->toArray()
                    ),
                Forms\Components\Placeholder::make('')
                    ->columnSpan(1),
                Forms\Components\Textarea::make('note')->label('Note')
                    ->columnSpanFull(),
                //
                Forms\Components\DatePicker::make('registration_date')
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
                Forms\Components\DatePicker::make('validation_date')
                    ->label('Data validazione')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn ($get) => $get('validated'))
                    ->columnSpan(2),
                Forms\Components\Select::make('validation_user_id')
                    ->label('Validato da')
                    ->relationship('validationUser', 'name')
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn ($get) => $get('validated'))
                    ->columnSpan(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('🔍 Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('passiveInvoice.supplier.denomination')
                    ->label('🔍 Fornitore')
                    ->limit(45)
                    ->tooltip(fn($record) => $record->passiveInvoice->supplier->denomination)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('passiveInvoice.docType.description')
                    ->label('🔍 Tipo documento')
                    ->limit(45)
                    ->tooltip(fn($record) => DocType::where('name', $record->passiveInvoice->doc_type)->first()->description)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('passiveInvoice.number')
                    ->label('🔍 Fattura')
                    ->getStateUsing(function ($record) {
                        $invoice = $record->passiveInvoice;
                        if (!$invoice) {
                            return 'Nessuna fattura';
                        }
                        return "{$invoice->number}/{$invoice->invoice_date->format('d-m-Y')}";
                    })
                    ->alignRight()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('🔍 Importo')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->sortable()
                    ->alignRight()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('')
                            ->money('EUR', true, 'it_IT'),
                    ])
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('🔍 Data pag.')
                    ->getStateUsing(function ($record) {
                        return $record->payment_date
                            ? Carbon::parse($record->payment_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // Proviamo a convertire la stringa "15/05/2023" in "2023-05-15"
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $search)->format('Y-m-d');
                            return $query->whereDate('payment_date', $date);
                        } catch (\Exception $e) {
                            // Se l'utente sta ancora scrivendo o il formato non è valido,
                            // cerchiamo come stringa parziale nel formato DB
                            return $query->where('payment_date', 'like', "%{$search}%");
                        }
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_date')
                    ->label('🔍 Data reg.')
                    ->getStateUsing(function ($record) {
                        return $record->registration_date
                            ? Carbon::parse($record->registration_date)->format('d/m/Y')
                            : 'Nessuna data';
                    })
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // Proviamo a convertire la stringa "15/05/2023" in "2023-05-15"
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $search)->format('Y-m-d');
                            return $query->whereDate('payment_date', $date);
                        } catch (\Exception $e) {
                            // Se l'utente sta ancora scrivendo o il formato non è valido,
                            // cerchiamo come stringa parziale nel formato DB
                            return $query->where('registration_date', 'like', "%{$search}%");
                        }
                    })
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('registrationUser.name')
                    ->label('🔍 Registrato da')
                    ->getStateUsing(fn ($record) => optional($record->registrationUser)->name ?? 'Nessun utente')
                    ->sortable()
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\ToggleColumn::make('validated')
                //     ->label('Validato')
                //     ->sortable()
                //     ->afterStateUpdated(function (\App\Models\PassivePayment $record, bool $state) {
                //         if ($state) {
                //             $record->validation_date = now();
                //             $record->validation_user_id = Auth::id();
                //         } else {
                //             // Se vuoi "annullare" la validazione quando il toggle viene disattivato
                //             $record->validation_date = null;
                //             $record->validation_user_id = null;
                //         }

                //         $record->save();
                //     }),
                Tables\Columns\IconColumn::make('validated')
                    ->label('Validato')
                    ->boolean()
                    ->sortable()
                    ->tooltip(fn (\App\Models\PassivePayment $record) => !auth()->user()->can('update', $record)
                        ? '' : ($record->validated
                        ? 'Annulla validazione pagamento.'
                        : 'Valida pagamento.')
                    )
                    ->disabled(function (\App\Models\PassivePayment $record) {
                        // Disabilita se l'utente NON può aggiornare il record
                        return !auth()->user()->can('update', $record);
                    })
                    ->action(
                        Tables\Actions\Action::make('toggleValidated')
                            ->requiresConfirmation()
                            ->disabled(function (\App\Models\PassivePayment $record) {
                                return !auth()->user()->can('update', $record);
                            })
                            ->modalHeading(fn (\App\Models\PassivePayment $record) => $record->validated
                                ? 'Rimuovere validazione?'
                                : 'Confermare validazione?'
                            )
                            ->modalDescription(fn (\App\Models\PassivePayment $record) => $record->validated
                                ? 'Il pagamento verrà marcato come non validato.'
                                : 'Il pagamento verrà marcato come validato.'
                            )
                            ->modalSubmitActionLabel('Conferma')
                            ->action(function (\App\Models\PassivePayment $record) {
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
            ])
            ->filtersFormWidth('3xl')
            ->filtersFormColumns(12)
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Fornitore')
                    // ->multiple()
                    ->searchable()
                    // ->preload()
                    ->columnSpanFull()
                    ->options(function () {
                        $suppliers = Supplier::select('suppliers.id', 'suppliers.denomination')
                            ->join('passive_invoices', 'suppliers.id', '=', 'passive_invoices.supplier_id')
                            ->distinct()
                            ->get()
                            ->pluck('denomination', 'id')
                            ->toArray();
                        return $suppliers;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('passiveInvoice', function (Builder $innerQuery) use ($data) {
                            $innerQuery->where('supplier_id', $data['value']);
                        });
                    })
                    ->getOptionLabelUsing(fn ($record) => $record?->description),
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
                        return $query->when($data['value'] === 'si', fn ($q) => $q->where('validated', true))
                                    ->when($data['value'] === 'no', fn ($q) => $q->where('validated', false));
                    })
                    ->columnSpan(4)
                    ->preload(),
                SelectFilter::make('select_doc_type')
                    ->label('Seleziona tipo documento')
                    // Definisco le opzioni prendendole da DocType
                    ->options(function () {
                        return DocType::orderBy('doc_group_id')->pluck('description', 'name')->toArray();
                    })
                    ->columnSpan(4)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    // Specifico noi come filtrare la query principale
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        // Entro nella relazione passiveInvoice e filtro sulla sua colonna doc_type
                        return $query->whereHas('passiveInvoice', function (Builder $innerQuery) use ($data) {
                            $innerQuery->whereIn('doc_type', $data['values']);
                        });
                    }),
                SelectFilter::make('exclude_doc_type')
                    ->label('Escludi tipo documento')
                    // Definisco le opzioni prendendole da DocType
                    ->options(function () {
                        return DocType::orderBy('doc_group_id')->pluck('description', 'name')->toArray();
                    })
                    ->columnSpan(4)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    // Specifico noi come filtrare la query principale
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        // Entro nella relazione passiveInvoice e filtro sulla sua colonna doc_type
                        return $query->whereHas('passiveInvoice', function (Builder $innerQuery) use ($data) {
                            $innerQuery->whereNotIn('doc_type', $data['values']);
                        });
                    }),
                Filter::make('payment_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('payment_from_date')
                            ->label('Pagamento da')
                            ->default(now()->year . '-01-01')
                            ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $set('payment_to_date', $state);
                                }
                            })
                            ->columnSpan(1),
                        DatePicker::make('payment_to_date')
                            ->label('Pagamento a')
                            ->default(now()->year . '-12-31')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(6)
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
                    }),
                Filter::make('registration_date_range')
                    ->columns(2)
                    ->form([
                        DatePicker::make('registration_from_date')
                            ->label('Registrazione da')
                            ->columnSpan(1),
                        DatePicker::make('registration_to_date')
                            ->label('Registrazione a')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(6)
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['registration_from_date'])) {
                            $query->whereDate('registration_date', '>=', $data['registration_from_date']);
                        }
                        if (! empty($data['registration_to_date'])) {
                            $query->whereDate('registration_date', '<=', $data['registration_to_date']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['registration_from_date'] && $data['registration_to_date']) {
                            return "Registrazione dal " . Carbon::parse($data['registration_from_date'])->format('d/m/Y') . " al " . Carbon::parse($data['registration_to_date'])->format('d/m/Y');
                        }
                        if ($data['registration_from_date']) {
                            return "Registrazione dal " . Carbon::parse($data['registration_from_date'])->format('d/m/Y');
                        }
                        if ($data['registration_to_date']) {
                            return "Registrazione al " . Carbon::parse($data['registration_to_date'])->format('d/m/Y');
                        }
                        return null;
                    }),
                SelectFilter::make('withholdings')
                    ->label('Ritenuta d\'acconto')
                    ->options([
                        'yes' => 'Con ritenuta',
                        'no' => 'Senza ritenuta',
                    ])
                    ->columnSpan(4)
                    ->query(function (Builder $query, array $data): Builder {
                        if (! isset($data['value'])) {
                            return $query;
                        }
                        // Entro nella relazione passiveInvoice e applico gli scope della fattura
                        return $query->when($data['value'] === 'yes', fn ($q) => $q->whereHas('passiveInvoice', fn (Builder $inner) => $inner->withholdings()))
                                    ->when($data['value'] === 'no', fn ($q) => $q->whereHas('passiveInvoice', fn (Builder $inner) => $inner->withoutWithholdings()));
                    }),
            ])
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
            'index' => Pages\ListPassivePayments::route('/'),
            'create' => Pages\CreatePassivePayment::route('/create'),
            'edit' => Pages\EditPassivePayment::route('/{record}/edit'),
            'view' => Pages\ViewPassivePayment::route('/{record}'),
        ];
    }

    protected static function getPassiveInvoiceLabel(PassiveInvoice $record): string
    {
        $supplierName = $record->supplier?->denomination ?? 'Fornitore sconosciuto';
        $date = $record->invoice_date ? $record->invoice_date->format('d/m/Y') : 'Data N.D.';
        $total = number_format($record->total, 2, ',', '.') . '€';
        $number = $record->number ?? 'S.N.';

        $residuo = $record->total - ($record->total_payment + $record->total_note);
        $add = ($residuo > 0 && ($record->total_payment > 0 || $record->total_note > 0))
            ? " [" . number_format($residuo, 2, ",", ".") . "€]"
            : '';

        $type = $record->docType->docGroup->name;
        $label = '';
        switch($type){
            case 'Preavvisi di fattura':
                $label = 'PF';
                break;
            case 'Fatture':
                $label = 'FT';
                break;
            case 'Note di variazione':
                $label = 'NV';
                break;
            case 'Autofatture':
                $label = 'AF';
                break;
        }

        return "{$supplierName} - {$label} {$number} del {$date} - Tot: {$total}" . $add;
    }
}
