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

    protected static ?string $navigationIcon = 'polaris-payment-icon';

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
                    ->getSearchResultsUsing(function (string $search) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query base con le stesse condizioni del relationship
                        $query = Invoice::query()
                            ->whereNotNull('contract_id')
                            ->where('sdi_status', '!=', 'da_inviare')
                            ->whereNull('parent_id');

                        // Cerca separatori (spazio, virgola, slash, trattino)
                        $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                        if (count($parts) >= 2) {
                            // Due o più valori: prendi i primi due e convertili a integer
                            $value1 = is_numeric($parts[0]) ? (int) $parts[0] : null;
                            $value2 = is_numeric($parts[1]) ? (int) $parts[1] : null;

                            if ($value1 !== null && $value2 !== null) {
                                // Prova number/year o year/number (match esatto)
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
                            // Un solo valore: cerca SOLO match esatto in number o year
                            if (is_numeric($parts[0])) {
                                $value = (int) $parts[0];
                                $query->where(function ($q) use ($value) {
                                    $q->where('number', $value)
                                    ->orWhere('year', $value);
                                });
                            }
                        }

                        return $query
                            ->with(['client', 'sectional'])
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
                            if ($invoice) {
                                $set('bank_account_id', $invoice->bank_account_id);
                            }
                        }
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->disabled(fn ($get) => !$get('invoice_id') || $get('validated'))
                    ->afterStateUpdated(function ($state, Get $get, $component) {
                        $invoice = Invoice::find($get('invoice_id'));
                        $newTotalPayment = $state + $invoice->total_payment;
                        $compare = $invoice->client?->type?->value == 'public' ? $invoice->no_vat_total : $invoice->total;

                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);

                        if($newTotalPayment > ($compare - $invoice->total_notes)){
                            Notification::make()
                                ->title('Attenzione! Con questo inserimento il totale dei pagamenti della fattura ' . $invoice->getNewInvoiceNumber() . ' eccederebbe il dovuto.')
                                ->danger()
                                ->persistent()
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
                    ->reactive()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        $invoice = Invoice::find($get('invoice_id'));
                        $paymentDate = $get('payment_date');

                        if ($paymentDate && $invoice && $paymentDate < $invoice->invoice_date) {
                            Notification::make()
                                ->title('Attenzione! La data del pagamento è inferiore alla data della fattura.')
                                ->danger()
                                ->persistent()
                                ->send();
                        }

                        if ($paymentDate && $paymentDate > today()) {
                            Notification::make()
                                ->title('Attenzione! La data del pagamento è successiva alla data di oggi.')
                                ->warning()
                                ->persistent()
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
                    ->columnSpan(2),
                Forms\Components\Select::make('bank_account_id')->label('Conto')
                    ->relationship(
                        name: 'bankAccount',
                        modifyQueryUsing: fn (Builder $query) =>
                        $query->where('company_id',Filament::getTenant()->id)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name} - $record->iban"
                    )
                    ->searchable()
                    ->required()
                    ->columnSpan(5)
                    ->preload(),
                Forms\Components\Placeholder::make('')
                    ->columnSpan(7),
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
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data pagamento')
                    ->getStateUsing(function ($record) {
                        return $record->registration_date
                            ? Carbon::parse($record->registration_date)->format('d/m/Y')
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
                                $label = strtoupper($client->subtype->getLabel()) . ' - ' . $client->denomination;
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
                    ->label('Competenze')
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
                SelectFilter::make('invoice_year')
                    ->label('Anno Fattura')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
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
                    }),
                SelectFilter::make('invoice_budget_year')
                    ->label('Anno Bilancio')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
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
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
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
