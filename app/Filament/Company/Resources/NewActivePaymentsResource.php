<?php

namespace App\Filament\Company\Resources;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use Filament\Forms\Form;
use App\Enums\ClientType;
use App\Models\Sectional;
use Filament\Tables\Table;
use App\Models\AccrualType;
use App\Models\ActivePayments;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use App\Models\NewActivePayments;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\NewActivePaymentsResource\Pages;
use App\Filament\Company\Resources\NewActivePaymentsResource\RelationManagers;

class NewActivePaymentsResource extends Resource
{
    protected static ?string $model = ActivePayments::class;

    public static ?string $pluralModelLabel = 'Pagamenti';

    public static ?string $modelLabel = 'Pagamento';

    protected static ?string $navigationIcon = 'polaris-payment-icon';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Fattura')
                    ->placeholder('Seleziona una fattura...')
                    ->relationship(
                        name: 'invoice',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn ($query) => $query->whereNotNull('flow')
                    )
                    ->getOptionLabelFromRecordUsing(function (Model $record) {
                        $cliente = $record->client?->denomination ?? 'Cliente sconosciuto';
                        $sectional = $record->sectional?->description ?? 'N/A';
                        $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                        $year = $record->year ?? '????';
                        return "{$cliente} - {$number}/{$sectional}/{$year}";
                    })
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->searchable(['number', 'section', 'year'])
                    ->live()
                    ->preload()
                    ->columnSpan(5),
                Forms\Components\TextInput::make('amount')
                    ->label('Importo')
                    ->required()
                    ->disabled(fn ($get) => $get('validated'))
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    // ->rules(['numeric', 'min:0'])
                    ->suffix('€')
                    ->columnSpan(2),
                DatePicker::make('payment_date')
                    ->label('Data pagamento')
                    ->disabled(fn ($get) => $get('validated'))
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
                DatePicker::make('registration_date')
                    ->label('Data registrazione')
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
                    ->disabled()
                    ->visible(fn ($get) => $get('validated'))
                    ->columnSpan(2),
                Forms\Components\Select::make('validated_by_user_id')
                    ->label('Validato da')
                    ->relationship('validationUser', 'name')
                    ->disabled()
                    ->visible(fn ($get) => $get('validated'))
                    ->columnSpan(3),
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
                            $record->validation_user_id = auth()->id;
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
                    ->columnSpan(2)
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
                    ->columnSpan(3)
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
                    ->columnSpan(2)
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
                SelectFilter::make('contract_accrual_type_id')
                    ->label('Competenza')
                    ->options(function () {
                        return AccrualType::query()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->attribute(null)
                    ->columnSpan(2)
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? [];

                        if (!empty($values)) {
                            return $query->whereHas('invoice.contract', function ($q) use ($values) {
                                $q->whereIn('accrual_type_id', $values);
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
            ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(8)
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
            'index' => Pages\ListNewActivePayments::route('/'),
            'create' => Pages\CreateNewActivePayments::route('/create'),
            'edit' => Pages\EditNewActivePayments::route('/{record}/edit'),
        ];
    }
}
