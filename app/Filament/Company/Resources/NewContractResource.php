<?php
namespace App\Filament\Company\Resources;

use App\Enums\InvoicingCicle;
use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Company;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\AccrualType;
use App\Models\NewContract;
use Filament\Facades\Filament;
use App\Enums\TenderPaymentType;
use Filament\Resources\Resource;
use Filament\Forms\Components\View;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use App\Filament\Company\Resources\NewContractResource\Pages;
use App\Filament\Company\Resources\NewContractResource\RelationManagers\ContractDetailsRelationManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class NewContractResource extends Resource
{
    protected static ?string $model = NewContract::class;
    public static ?string $pluralModelLabel = 'Contratti';
    public static ?string $modelLabel = 'Contratto';
    protected static ?string $navigationIcon = 'tabler-contract';
    protected static ?string $navigationGroup = 'Fatturazione attiva';
    protected static ?int $navigationSort = 3;
    protected static ?int $navigationGroupSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            // ->disabled(function ($record): bool { return $record !== null && !Auth::user()->isManager(); })
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->hintAction(
                        Action::make('Nuovo')
                            ->icon('ri-user-2-line')
                            ->form(fn(Form $form) => ClientResource::modalForm($form))
                            ->modalWidth('7xl')
                            ->modalHeading('')
                            ->action(fn (array $data, Client $client, Set $set) => NewContractResource::saveClient($data, $client, $set))
                            ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Company\Resources\NewContractResource\Pages\EditNewContract)
                    )
                    ->relationship(
                        name: 'client',
                        titleAttribute: 'denomination',
                        modifyQueryUsing: function (Builder $query, Forms\Components\Select $component) { $query->whereRaw('1 = 0'); }
                    )
                    ->getSearchResultsUsing(function (string $search) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query base con le stesse condizioni del relationship
                        $query = Client::query();

                        // Cerca separatori (spazio, virgola, slash, trattino)
                        $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                        if (count($parts) >= 2) {
                            // Cerca ogni "parte" all'interno del campo denomination
                            $query->where(function ($q) use ($parts) {
                                foreach ($parts as $part) {
                                    $q->where('denomination', 'LIKE', "%{$part}%");
                                }
                            });
                        } elseif (count($parts) === 1) {
                            // Un solo valore: cerca SOLO match esatto in number o year
                            $value = $parts[0];
                            $query->where(function ($q) use ($value) {
                                $q->where('denomination', 'LIKE', "%{$value}%");
                            });
                        }

                        return $query
                            ->orderBy('denomination', 'asc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                $denomination = $record->denomination ?? 'N/A';
                                // $label = strtoupper("{$subtype}") . " - $denomination";
                                $label = $denomination;

                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function (?int $value) {
                        if (!$value) {
                            return null;
                        }
                        $record = Client::find($value);

                        if (!$record) {
                            return null;
                        }

                        // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                        return $record->denomination;
                    })
                    ->getOptionLabelFromRecordUsing(
                        // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                        fn (Model $record) => $record->denomination
                    )
                    ->required()
                    ->searchable('denomination')
                    ->live()
                    // ->preload()
                    ->optionsLimit(5)
                    // ->autofocus(function ($record): bool { return $record !== null && Auth::user()->isManager(); })
                    ->columnSpan(5),
                DatePicker::make('start_validity_date')
                    ->label('Inizio Validità')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required()
                    ->date()
                    ->columnSpan(2),
                DatePicker::make('end_validity_date')
                    ->label('Fine Validità')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->date()
                    ->columnSpan(2),
                Placeholder::make('second')
                    ->label('')
                    ->columnSpan(1),
                Forms\Components\Toggle::make('closed')
                    ->label('Contratto chiuso')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpan(2),
                Forms\Components\Select::make('tax_types')
                    ->label('Entrate')
                    ->options(TaxType::class)
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    // ->rules(['array', 'in:'.implode(',', collect(TaxType::cases())->pluck('value')->toArray())])
                    ->columnSpan(4),
                Forms\Components\Select::make('accrual_types')
                    ->label('Gestioni')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(\App\Models\AccrualType::pluck('name', 'id'))
                    ->formatStateUsing(function ($record) {
                        if (!$record) return [];

                        $raw = $record->getRawOriginal('accrual_types');                                        // bypasso il getter

                        if (is_string($raw)) {
                            $raw = json_decode($raw, true) ?: [];
                        }

                        return is_array($raw) ? array_map('intval', $raw) : [];
                    })
                    ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_map('intval', $state) : [])
                    ->rules([
                        'required',
                        'array',
                        'array.*' => Rule::in(
                            \App\Models\AccrualType::pluck('id')->map('strval')->toArray()
                        ),
                    ])
                    ->columnSpan(4),
                Forms\Components\Select::make('manage_types')
                    ->label('Servizi')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(\App\Models\ManageType::pluck('name', 'id'))
                    ->formatStateUsing(function ($record) {
                        if (!$record) return [];

                        $raw = $record->getRawOriginal('manage_types');                                        // bypasso il getter

                        if (is_string($raw)) {
                            $raw = json_decode($raw, true) ?: [];
                        }

                        return is_array($raw) ? array_map('intval', $raw) : [];
                    })
                    ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_map('intval', $state) : [])
                    ->rules([
                        'required',
                        'array',
                        'array.*' => Rule::in(
                            \App\Models\ManageType::pluck('id')->map('strval')->toArray()
                        ),
                    ])
                    ->columnSpan(4),
                Forms\Components\Select::make('payment_type')
                    ->label('Remunerazione')
                    ->options(TenderPaymentType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('amount')
                    ->label('Capienza (iva esclusa)')
                    ->required()
                    ->columnSpan(3)
                    ->inputMode('decimal')
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->afterStateUpdated(function ($state, $component) {
                        $clean = preg_replace('/[^\d,\.-]/', '', $state);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $component->state($formatted);
                    })
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€'),
                Forms\Components\Toggle::make('reinvoice')
                    ->label('Rifatturazione spese postali')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpan(3),
                Forms\Components\Select::make('invoicing_cycle')
                    ->label('Periodicità fatturazione')
                    ->options(InvoicingCicle::class)
                    ->required()
                    ->preload()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('office_name')
                    ->label('Denominazione UO')
                    ->required()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('office_code')
                    ->label('Codice Univoco')
                    ->required()
                    ->columnSpan(3),
                View::make('links.ipa-link')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('cig_code')
                    ->label('CIG (Rif. contratto)')
                    ->hintIcon('heroicon-o-information-circle', tooltip: "Il codice CIG deve essere univoco e di 10 caratteri. In caso di contratto senza CIG (solo con privati o con enti pubblici per il recupero delle spese postali) si deve inserire il dato preceduto da '#' e la procedura elude questo controllo.")
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state){
                        if (strpos($state, '#') === false && strlen($state) !== 10) {
                                Notification::make()
                                    ->title('Errore! Il codice CIG deve essere lungo 10 caratteri')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                    })
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            // Se non c'è il cancelletto e la lunghezza non è 15
                            if (strpos($value, '#') === false && strlen($value) !== 10) {
                                $fail("Il codice CIG deve essere lungo 10 caratteri");
                            }
                        },
                    ])
                    ->columnSpan(2),
                Forms\Components\TextInput::make('cup_code')
                    ->label('CUP')
                    // ->required()
                    ->columnSpan(2),
                // Forms\Components\FileUpload::make('new_contract_copy_path')->label('Copia contratto')
                //     ->live()
                //     ->disk('public')
                //     ->directory('new_contracts')
                //     ->visibility('public')
                //     ->acceptedFileTypes(['application/pdf', 'image/*'])
                //     ->afterStateUpdated(function (Set $set, $state) {
                //         if (!empty($state)) {
                //             $set('new_contract_copy_date', now()->toDateString());
                //         } else {
                //             $set('new_contract_copy_date', null);
                //         }
                //     })
                //     ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get, $record) {
                //         $client = Client::find($get('client_id'))->denomination;
                //         $taxTypes = implode('_', array_map(fn($val) => TaxType::from($val)->getLabel(), $get('tax_types')));
                //         $cig = $get('cig_code');
                //         $extension = $file->getClientOriginalExtension();
                //         return sprintf('%s_CONTRATTO_%s_%s.%s', $client, $taxTypes, $cig, $extension);
                //     })
                //     ->columnSpan(5),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(10),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('view_new_contract_copy')
                        ->label('Contratto in vigore')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->new_contract_copy_path ? Storage::url($record->new_contract_copy_path) : null)
                        ->url(fn($record): ?string => $record->new_contract_copy_path ? Storage::temporaryUrl($record->new_contract_copy_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => $record && $record->new_contract_copy_path)
                        ->color('primary'),
                ])
                ->columnSpan(2),
                // DatePicker::make('new_contract_copy_date')
                //     ->readonly()
                //     ->dehydrated()
                //     ->label('Data caricamento')
                //     ->date()
                //     ->visible(fn(Get $get, $record): bool => $record && $record->new_contract_copy_path || $get('new_contract_copy_path'))
                //     ->columnSpan(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_types')
                    ->label('Entrate')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CDS' => 'info',
                        'ICI' => 'warning',
                        'IMU' => 'success',
                        'LIBERO' => 'danger',
                        'PARK' => 'info',
                        'PUB' => 'info',
                        'TARI' => 'primary',
                        'TEP' => 'primary',
                        'TOSAP' => 'warning',
                        default => 'gray'
                    })
                    ->separator(', ')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereJsonContains('tax_types', $search);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('accrual_types')
                    ->label('Gestioni')
                    ->badge()
                    ->color('primary')
                    ->separator(', ')
                    ->searchable(query: function (Builder $query, string $search) {
                        $accrualTypeIds = AccrualType::where('name', 'like', "%{$search}%")->pluck('id')->toArray();
                        $query->whereJsonContains('accrual_types', $accrualTypeIds);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Remunerazione')
                    ->searchable()
                    ->color('black')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_validity_date')
                    ->label('Inizio contratto')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_validity_date')
                    ->label('Fine contratto')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . " €"),
            ])
            ->filters([
                SelectFilter::make('client_id')->label('Cliente')
                    // ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getSearchResultsUsing(function (string $search) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query base con le stesse condizioni del relationship
                        $query = Client::query();

                        // Cerca separatori (spazio, virgola, slash, trattino)
                        $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                        if (count($parts) >= 2) {
                            // Cerca ogni "parte" all'interno del campo denomination
                            $query->where(function ($q) use ($parts) {
                                foreach ($parts as $part) {
                                    $q->where('denomination', 'LIKE', "%{$part}%");
                                }
                            });
                        } elseif (count($parts) === 1) {
                            // Un solo valore: cerca SOLO match esatto in number o year
                            $value = $parts[0];
                            $query->where(function ($q) use ($value) {
                                $q->where('denomination', 'LIKE', "%{$value}%");
                            });
                        }

                        return $query
                            ->orderBy('denomination', 'asc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                $denomination = $record->denomination ?? 'N/A';
                                // $label = strtoupper("{$subtype}") . " - $denomination";
                                $label = $denomination;

                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function (?int $value) {
                        if (!$value) {
                            return null;
                        }
                        $record = Client::find($value);

                        if (!$record) {
                            return null;
                        }

                        // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                        return $record->denomination;
                    })
                    // ->getOptionLabelFromRecordUsing(
                    //     fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                    // )
                    ->searchable()
                    // ->preload()
                    ->optionsLimit(5),
                SelectFilter::make('tax_types')
                    ->label('Entrate')
                    ->options(TaxType::class)
                    ->multiple()
                    ->preload(),
                SelectFilter::make('accrual_types')
                    ->label('Gestioni')
                    ->options(AccrualType::pluck('name', 'id')->toArray())
                    ->multiple()
                    ->preload(),
                SelectFilter::make('payment_type')->label('Remunerazione')->options(TenderPaymentType::class)
                    ->multiple()->preload(),
            // ], layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(4)
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => Auth::user()->isManager()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => Auth::user()->isManager()),
                ]),
            ])
            ->defaultSort('start_validity_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ContractDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewContracts::route('/'),
            'create' => Pages\CreateNewContract::route('/create'),
            'edit' => Pages\EditNewContract::route('/{record}/edit'),
            'view' => Pages\ViewNewContract::route('/{record}'),
        ];
    }

    public static function getNavigationSort(): ?int
    {
        return 9;
    }

    public static function modalForm(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    // ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getSearchResultsUsing(function (string $search) {
                        // Rimuovi spazi multipli e trim
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query base con le stesse condizioni del relationship
                        $query = Client::query();

                        // Cerca separatori (spazio, virgola, slash, trattino)
                        $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                        if (count($parts) >= 2) {
                            // Cerca ogni "parte" all'interno del campo denomination
                            $query->where(function ($q) use ($parts) {
                                foreach ($parts as $part) {
                                    $q->where('denomination', 'LIKE', "%{$part}%");
                                }
                            });
                        } elseif (count($parts) === 1) {
                            // Un solo valore: cerca SOLO match esatto in number o year
                            $value = $parts[0];
                            $query->where(function ($q) use ($value) {
                                $q->where('denomination', 'LIKE', "%{$value}%");
                            });
                        }

                        return $query
                            ->orderBy('denomination', 'asc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                $denomination = $record->denomination ?? 'N/A';
                                // $label = strtoupper("{$subtype}") . " - $denomination";
                                $label = $denomination;

                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function (?int $value) {
                        if (!$value) {
                            return null;
                        }
                        $record = Client::find($value);

                        if (!$record) {
                            return null;
                        }

                        // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                        return $record->denomination;
                    })
                    ->getOptionLabelFromRecordUsing(
                        // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                        fn (Model $record) => $record->denomination
                    )
                    ->required()
                    ->searchable('denomination')
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(5),
                Forms\Components\Select::make('tax_types')
                    ->label('Entrate')
                    ->options(TaxType::class)
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->rules(['array', 'in:'.implode(',', collect(TaxType::cases())->pluck('value')->toArray())])
                    ->columnSpan(3),
                DatePicker::make('start_validity_date')
                    ->label('Inizio Validità')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required()
                    ->date()
                    ->columnSpan(2),
                DatePicker::make('end_validity_date')
                    ->label('Fine Validità')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->date()
                    ->columnSpan(2),
                Forms\Components\Select::make('accrual_types')
                    ->label('Gestioni')
                    ->options(AccrualType::pluck('name', 'id')->toArray())
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->rules(['array', 'exists:accrual_types,id'])
                    ->columnSpan(3),
                Forms\Components\Select::make('payment_type')
                    ->label('Remunerazione')
                    ->options(TenderPaymentType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->label('Importo')
                    ->columnSpan(3)
                    ->inputMode('decimal')
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                    ->suffix('€'),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(3),
                Forms\Components\TextInput::make('office_name')
                    ->label('Nome ufficio')
                    ->required()
                    ->columnSpan(3),
                Forms\Components\TextInput::make('office_code')
                    ->label('Codice ufficio')
                    ->required()
                    ->columnSpan(3),
                View::make('links.ipa-link')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('cig_code')
                    ->label('CIG')
                    ->required()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('cup_code')
                    ->label('CUP')
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Select::make('invoicing_cycle')
                    ->label('Periodicità fatturazione')
                    ->options(InvoicingCicle::class)
                    ->required()
                    ->preload()
                    ->columnSpan(3),
                // Forms\Components\FileUpload::make('new_contract_copy_path')->label('Copia contratto')
                //     ->live()
                //     ->disk('public')
                //     ->directory('new_contracts')
                //     ->visibility('public')
                //     ->acceptedFileTypes(['application/pdf', 'image/*'])
                //     ->afterStateUpdated(function (Set $set, $state) {
                //         if (!empty($state)) {
                //             $set('new_contract_copy_date', now()->toDateString());
                //         } else {
                //             $set('new_contract_copy_date', null);
                //         }
                //     })
                //     ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get, $record) {
                //         $client = Client::find($get('client_id'))->denomination;
                //         $taxTypes = implode('_', array_map(fn($val) => TaxType::from($val)->getLabel(), $get('tax_types')));
                //         $cig = $get('cig_code');
                //         $extension = $file->getClientOriginalExtension();
                //         return sprintf('%s_CONTRATTO_%s_%s.%s', $client, $taxTypes, $cig, $extension);
                //     })
                //     ->columnSpan(5),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(5),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('view_new_contract_copy')
                        ->label('Contratto in vigore')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->new_contract_copy_path ? Storage::url($record->new_contract_copy_path) : null)
                        ->url(fn($record): ?string => $record->new_contract_copy_path ? Storage::temporaryUrl($record->new_contract_copy_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => $record && $record->new_contract_copy_path)
                        ->color('primary'),
                ])
                ->columnSpan(2),
                // DatePicker::make('new_contract_copy_date')
                //     ->readonly()
                //     ->dehydrated()
                //     ->label('Data caricamento')
                //     ->date()
                //     ->visible(fn(Get $get, $record): bool => $record && $record->new_contract_copy_path || $get('new_contract_copy_path'))
                //     ->columnSpan(2),
            ]);
    }

    public static function saveClient(array $data, Client $client, Set $set): void
    {
        $client->company_id = Filament::getTenant()->id;
        $client->type = $data['type'] ?? null;
        $client->subtype = $data['subtype'] ?? null;
        $client->denomination = $data['denomination'] ?? null;
        $client->state_id = $data['state_id'] ?? null;
        $client->address = $data['address'] ?? null;
        $client->zip_code = $data['zip_code'] ?? null;
        $client->city_id = $data['city_id'] ?? null;
        $client->place = $data['place'] ?? null;
        $client->tax_code = $data['tax_code'] ?? null;
        $client->vat_code = $data['vat_code'] ?? null;
        $client->ipa_code = $data['ipa_code'] ?? null;
        $client->phone = $data['phone'] ?? null;
        $client->email = $data['email'] ?? null;
        $client->pec = $data['pec'] ?? null;
        $client->save();

        $set('client_id', $client->id);
        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }

    public static function saveContract(array $data, NewContract $contract, Set $set): void
    {
        $contract->company_id = Filament::getTenant()->id;
        $contract->client_id = $data['client_id'];
        $contract->tax_types = $data['tax_types'];
        $contract->start_validity_date = $data['start_validity_date'];
        $contract->end_validity_date = $data['end_validity_date'];
        $contract->accrual_types = $data['accrual_types'];
        $contract->payment_type = $data['payment_type'];
        $contract->cig_code = $data['cig_code'];
        $contract->cup_code = $data['cup_code'];
        $contract->office_code = $data['office_code'];
        $contract->office_name = $data['office_name'];
        $contract->amount = $data['amount'];
        $contract->invoicing_cycle = $data['invoicing_cycle'] ?? null;
        $contract->new_contract_copy_path = $data['new_contract_copy_path'] ?? null;
        $contract->new_contract_copy_date = $data['new_contract_copy_date'] ?? null;
        $contract->reinvoice = $data['reinvoice'] ?? false;
        $contract->save();
        $set('contract_id', $contract->id);
        Notification::make()
            ->title('Contratto salvato con successo')
            ->success()
            ->send();
    }
}
