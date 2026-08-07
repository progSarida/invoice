<?php
namespace App\Filament\Company\Resources;

use App\Enums\BailType;
use App\Enums\TaxType;
use App\Filament\Company\Resources\BailResource\Pages;
use App\Filament\Company\Resources\BailResource\RelationManagers;
use App\Filament\Company\Resources\BailResource\RelationManagers\BailDetailsRelationManager;
use App\Models\Agency;
use App\Models\Bail;
use App\Models\Client;
use App\Models\Insurance;
use App\Models\NewContract;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
// use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Query\JoinClause;

class BailResource extends Resource
{
    protected static ?string $model = Bail::class;
    public static ?string $pluralModelLabel = 'Polizze';
    public static ?string $modelLabel = 'Polizza';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Polizze';
    protected static ?int $navigationSort = 1;
    protected static ?int $navigationGroupSort = 3;
    protected static ?string $recordTitleAttribute = 'bill_number';

    public static function form(Form $form): Form
    {
        // 1. Definiamo la subquery per trovare l'ultima data di dettaglio per ogni contratto
        // Lo facciamo fuori dalle closure principali per riutilizzarlo e per chiarezza
        $latestDetailSubquery = \App\Models\ContractDetail::query()
            ->selectRaw('contract_id, MAX(date) as latest_detail_date')
            ->groupBy('contract_id')
            ->toBase();

        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Select::make('client_id')->label('Cliente')
                    ->hintAction(
                        Action::make('Nuovo')
                            ->icon('ri-user-2-line')
                            ->form(fn (Form $form) => ClientResource::modalForm($form))
                            ->modalHeading('')
                            ->modalWidth('7xl')
                            ->action(fn (array $data, Client $client, Get $get, Set $set) => BailResource::saveClient($data, $client, $get, $set))
                            ->visible(fn (?Model $record): bool => $record === null)
                    )
                    ->relationship(name: 'client', titleAttribute: 'denomination')
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
                    ->columnSpan(5),
                Forms\Components\Select::make('tax_types') // MODIFICA: Rinominato da 'tax_type' a 'tax_types'
                    ->label('Tipo Entrata')
                    // ->options(\App\Enums\TaxType::class)
                    ->options(function (Get $get) {
                        $clientId = $get('client_id');
                        if (empty($clientId)) {
                            return TaxType::class;
                        }

                        // Recupera i contratti del cliente
                        $contracts = \App\Models\NewContract::where('client_id', $clientId)
                                        ->where('closed', false)->get();

                        if(count($contracts) == 0) return [];

                        // Crea una mappa label => value per tutti i TaxType
                        $labelToValue = [];
                        foreach (TaxType::cases() as $case) {
                            $labelToValue[strtolower($case->getLabel())] = $case->value;
                        }

                        // \Log::info('Label to Value map:', $labelToValue);

                        // Raccogli tutti i tax_types dal database
                        $taxTypesFromDb = [];
                        foreach ($contracts as $contract) {
                            if (is_array($contract->tax_types)) {
                                $taxTypesFromDb = array_merge($taxTypesFromDb, $contract->tax_types);
                            }
                        }

                        // \Log::info('Tax types from DB:', $taxTypesFromDb);

                        // Converti i label in value
                        $taxTypeValues = [];
                        foreach ($taxTypesFromDb as $label) {
                            $normalizedLabel = strtolower($label);
                            if (isset($labelToValue[$normalizedLabel])) {
                                $taxTypeValues[] = $labelToValue[$normalizedLabel];
                            }
                        }

                        $taxTypeValues = array_unique(array_filter($taxTypeValues));

                        // \Log::info('Converted to values:', $taxTypeValues);

                        if (empty($taxTypeValues)) {
                            return TaxType::class;
                        }

                        // Crea l'array di opzioni
                        $options = [];
                        foreach (TaxType::cases() as $case) {
                            if (in_array($case->value, $taxTypeValues)) {
                                $options[$case->value] = $case->getLabel();
                            }
                        }

                        // \Log::info('Final options:', $options);

                        return empty($options) ? TaxType::class : $options;
                    })
                    ->multiple() // MODIFICA: Aggiunto per consentire selezione multipla
                    ->afterStateUpdated(function (Get $get, Set $set) { // MODIFICA: Aggiornato per gestire array
                        if (empty($get('client_id')) || empty($get('tax_types'))) {
                            $set('contract_id', null);
                        }
                    })
                    ->placeholder('')
                    ->searchable()
                    ->live()
                    ->preload()
                    ->columnSpan(2),
                // Forms\Components\Select::make('contract_id')->label('Contratto')
                //     ->relationship(
                //         name: 'contract',
                //         modifyQueryUsing: fn (Builder $query, Get $get) => $query
                //             ->where('client_id', $get('client_id'))
                //             ->when($get('tax_types'), function ($q, $taxTypes) {
                //                 foreach ($taxTypes as $taxType) {
                //                     $q->whereJsonContains('tax_types', $taxType);
                //                 }
                //             })
                //     )
                //     ->getOptionLabelFromRecordUsing(
                //         fn (Model $record) => "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code})"
                //     )
                //     ->afterStateUpdated(function (Set $set, $state) {
                //         if ($state) {
                //             $contract = NewContract::find($state);
                //             $set('cig_code', $contract->cig_code);
                //         }
                //     })
                //     ->disabled(fn (Get $get): bool => !filled($get('client_id')) || !filled($get('tax_types')))
                //     ->searchable('cig_code')
                //     ->live()
                //     ->preload()
                //     ->optionsLimit(5)
                //     ->columnSpan(3),
                Forms\Components\Select::make('contract_id')->label('Contratto')
                    ->relationship(
                        name: 'contract',
                        modifyQueryUsing: function (Builder $query, Get $get, $livewire) use ($latestDetailSubquery) {
                            $query->where('client_id', $get('client_id'))
                                ->when($get('tax_types'), function ($q, $taxTypes) {
                                    foreach ($taxTypes as $taxType) {
                                        $q->whereJsonContains('tax_types', $taxType);
                                    }
                                });

                            // *** JOIN con subquery per calculated_year ***
                            $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                            });

                            // Applico i filtri di validità solo in fase di creazione
                            // if ($livewire instanceof \Filament\Resources\Pages\CreateRecord) {
                            //     $query->where(function ($q) {
                            //         $q->whereNull('start_validity_date')
                            //             ->orWhere(function ($q2) {
                            //                 $q2->where('start_validity_date', '<=', today())
                            //                     ->where('end_validity_date', '>=', today());
                            //             });
                            //     });
                            // }

                            if ($livewire instanceof \Filament\Resources\Pages\CreateRecord) {
                                // $query->where(function ($q) {
                                //     $q->whereNull('start_validity_date')
                                //         ->orWhere(function ($q2) {
                                //             $q2->where('start_validity_date', '<=', today())
                                //                 ->where(function ($q3) {
                                //                     // Contratti con end_validity_date valida
                                //                     $q3->where('end_validity_date', '>=', today())
                                //                         // OPPURE end_validity_date null con capienza residua
                                //                         ->orWhere(function ($q4) {
                                //                             $q4->whereNull('end_validity_date')
                                //                                 ->whereRaw('amount > (SELECT COALESCE(SUM(no_vat_total), 0) FROM invoices WHERE invoices.contract_id = new_contracts.id)');
                                //                         });
                                //                 });
                                //         });
                                // });
                                $query->where('closed', false);
                            }

                            // Seleziona tutte le colonne e aggiungi calculated_year
                            $query->select('new_contracts.*')
                                ->selectRaw('
                                    COALESCE(
                                        YEAR(new_contracts.start_validity_date),
                                        YEAR(latest_details.latest_detail_date)
                                    ) AS calculated_year'
                                )
                                ->distinct();

                            // Ordina per anno calcolato DESC
                            $query->orderByRaw('calculated_year DESC');

                            return $query;
                        }
                    )
                    ->getSearchResultsUsing(function (string $search, Get $get) use ($latestDetailSubquery) {
                        // Pulisci la stringa di ricerca
                        $search = trim(preg_replace('/\s+/', ' ', $search));

                        // Query di base con JOIN
                        $query = \App\Models\NewContract::query();

                        $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                            $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                        });

                        // Filtri di base
                        $query->where('client_id', $get('client_id'))
                            ->when($get('tax_types'), function ($q, $taxTypes) {
                                foreach ($taxTypes as $taxType) {
                                    $q->whereJsonContains('tax_types', $taxType);
                                }
                            });

                        // Filtro di ricerca
                        $query->where(function ($q) use ($search) {
                            // Cerca per CIG CODE
                            $q->where('new_contracts.cig_code', 'LIKE', "%{$search}%");

                            // Cerca per ANNO CALCOLATO (se numerico)
                            if (is_numeric($search)) {
                                $q->orWhereRaw('
                                    YEAR(COALESCE(
                                        new_contracts.start_validity_date,
                                        latest_details.latest_detail_date
                                    )) = ?', [$search]
                                );
                            }
                        });

                        // Selezione colonne
                        $query->select('new_contracts.*')
                            ->selectRaw('
                                COALESCE(
                                    YEAR(new_contracts.start_validity_date),
                                    YEAR(latest_details.latest_detail_date)
                                ) AS calculated_year'
                            )
                            ->distinct();

                        // Ordinamento
                        $query->orderByRaw('calculated_year DESC')
                            ->orderBy('new_contracts.id', 'DESC');

                        // Risultati
                        return $query
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($record) {
                                $label = "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code}) - {$record->calculated_year}";
                                return [$record->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value) use ($latestDetailSubquery) {
                        if (!$value) return null;

                        $contract = \App\Models\NewContract::query()
                            ->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                            })
                            ->select('new_contracts.*')
                            ->selectRaw('
                                COALESCE(
                                    YEAR(new_contracts.start_validity_date),
                                    YEAR(latest_details.latest_detail_date)
                                ) AS calculated_year'
                            )
                            ->where('new_contracts.id', $value)
                            ->first();

                        if (!$contract) return null;

                        return "{$contract->office_name} ({$contract->office_code})\nCIG: ({$contract->cig_code}) - {$contract->calculated_year}";
                    })
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nCIG: ({$record->cig_code}) - {$record->calculated_year}"
                    )
                    ->afterStateUpdated(function (Set $set, $state) {
                        if ($state) {
                            $contract = NewContract::find($state);
                            $lastDetail = $contract->lastDetail()->first();

                            // Imposta cig_code come nella select semplice
                            $set('cig_code', $contract?->cig_code);
                            // mostra descrizione contratto
                            $set('aid', $contract->lastDetail?->description);

                            // Controllo dettagli come nel codice complesso
                            if (!$lastDetail) {
                                Notification::make()
                                    ->title('Attenzione! E\' stato selezionato un contratto senza dettagli.')
                                    ->warning()
                                    ->duration(6000)
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai al contratto')
                                            ->url(NewContractResource::getUrl('edit', ['record' => $contract?->id]))
                                            ->openUrlInNewTab()
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                        }
                    })
                    ->disabled(fn (Get $get): bool => !filled($get('client_id')) || !filled($get('tax_types')))
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('cig_code')->label('CIG')
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\Textarea::make('aid')->label('Descrizione contratto')
                    ->disabled()
                    ->visible(fn(Get $get): bool => filled($get('contract_id')))
                    ->formatStateUsing(function ($state, Get $get, $record) {
                        $contractId = $get('contract_id') ?? $record?->contract_id;

                        if ($contractId) {
                            $contract = \App\Models\NewContract::find($contractId);
                            return $contract?->lastDetail?->description;
                        }

                        return null;
                    })
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Forms\Components\Select::make('insurance_id')
                    ->hintAction(
                        Action::make('Nuova')
                            ->icon('ri-contract-line')
                            ->form(fn (Form $form) => InsuranceResource::modalForm($form))
                            ->modalHeading('')
                            ->modalWidth('4xl')
                            ->action(fn (array $data, Insurance $insurance, Get $get, Set $set) => BailResource::saveInsurance($data, $insurance, $get, $set))
                            ->visible(fn (?Model $record): bool => $record === null)
                    )
                    ->label('Compagnia assicurativa')
                    ->required()
                    ->options(function () {
                        return \App\Models\Insurance::query()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('agency_id', null);
                    })
                    ->columnSpan(4),
                Forms\Components\Select::make('agency_id')
                    ->hintAction(
                        Action::make('Nuova')
                            ->icon('phosphor-house-light')
                            ->form(fn (Form $form) => AgencyResource::modalForm($form))
                            ->modalHeading('')
                            ->modalWidth('4xl')
                            ->action(fn (array $data, Agency $agency, Get $get, Set $set) => BailResource::saveAgency($data, $agency, $get, $set))
                            ->visible(fn (?Model $record): bool => $record === null)
                    )
                    ->label('Agenzia')
                    ->required()
                    ->options(function () {
                        return \App\Models\Agency::query()
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->live()
                    ->options(function (callable $get) {
                        $insuranceId = $get('insurance_id');
                        return \App\Models\Agency::query()
                            ->when($insuranceId, fn ($query) => $query->where('insurance_id', $insuranceId))
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $agency = \App\Models\Agency::find($state);
                            if ($agency && $agency->insurance_id) {
                                $set('insurance_id', $agency->insurance_id);
                            }
                        }
                    })
                    ->columnSpan(4),
                Forms\Components\Select::make('bail_type')->label('Tipo Polizza')
                    ->columnSpan(3)
                    ->required()
                    ->live()
                    ->options(\App\Enums\BailType::class)
                    ->nullable(),
                Forms\Components\TextInput::make('bill_number')->label('Numero Polizza')
                    ->required()
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->maxLength(255)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('bill_date')->label('Data Polizza')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required()
                    ->columnSpan(2),
                // Forms\Components\DatePicker::make('release_date')->label('Data Rilascio')
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->columnSpan(2)
                //     ->nullable(),
                Forms\Components\TextInput::make('year_duration')->label('Anni')
                    ->maxLength(255)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(1),
                Forms\Components\TextInput::make('month_duration')->label('Mesi')
                    ->maxLength(255)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(1),
                Forms\Components\TextInput::make('day_duration')->label('Giorni')
                    ->maxLength(255)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(1),
                Forms\Components\TextInput::make('first_premium')
                    ->label(fn (Get $get) => $get('bail_type') == BailType::BAIL->value ? 'Importo Premio iniziale' : 'Importo Premio')
                    ->columnSpan(2)
                    ->required()
                    // ->numeric()
                    ->live(onBlur: true)
                    ->debounce(3000)
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
                    // ->nullable()
                    ->prefix('€'),
                Forms\Components\TextInput::make('renewal_premium')->label('Importo premio per rinnovo')
                    ->columnSpan(3)
                    ->required()
                    ->visible(fn (Get $get) => $get('bail_type') == BailType::BAIL->value)
                    // ->numeric()
                    ->live(onBlur: true)
                    ->debounce(3000)
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
                    // ->nullable()
                    ->prefix('€'),
                Placeholder::make('')
                    ->label('')
                    ->visible(fn (Get $get) => $get('bail_type') == BailType::INSURANCE->value)
                    ->columnSpan(2),
                Forms\Components\FileUpload::make('bill_attachment_path')->label('Allegato Polizza')
                    ->live()
                    // ->disk('public')
                    ->directory('bail/bill-attachments')
                    // ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file, Get $get): string => Client::find($get('client_id'))->denomination . '_' . $get('bill_number') . '_Polizza.' . $file->getClientOriginalExtension()
                    )
                    ->columnSpan(4)
                    ->extraAttributes(['class' => 'file-upload-with-preview']),
                Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('view_condition_attachment')
                        // ->label('Visualizza')
                        ->label('Polizza')
                        ->tooltip('Visualizza polizza')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->bill_attachment_path ? Storage::url($record->bill_attachment_path) : null)
                        ->url(fn($record): ?string => $record && $record->bill_attachment_path ? Storage::temporaryUrl($record->bill_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->hidden(fn ($record) => !$record || !$record->bill_attachment_path),
                ])
                ->columnSpan(2),
                Forms\Components\FileUpload::make('condition_attachment_path')->label('Allegato Condizioni')
                    ->live()
                    // ->disk('public')
                    ->directory('bail/bill-attachments')
                    // ->visibility('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file, Get $get): string => Client::find($get('client_id'))->denomination . '_' . $get('bill_number') . '_Condizioni.' . $file->getClientOriginalExtension()
                    )
                    ->columnSpan(4)
                    ->extraAttributes(['class' => 'file-upload-with-preview']),
                Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('view_condition_attachment')
                        // ->label('Visualizza')
                        ->label('Condizioni')
                        ->tooltip('Visualizza condizioni')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->condition_attachment_path ? Storage::url($record->condition_attachment_path) : null)
                        ->url(fn($record): ?string => $record && $record->condition_attachment_path ? Storage::temporaryUrl($record->condition_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->hidden(fn ($record) => !$record || !$record->condition_attachment_path),
                ])
                ->columnSpan(2),
                // Forms\Components\DatePicker::make('bill_start')->label('Inizio Polizza')
                //     ->required()
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->columnSpan(2),
                // Forms\Components\DatePicker::make('bill_deadline')->label('Scadenza Polizza')
                //     ->required()
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->columnSpan(2),
                // Forms\Components\TextInput::make('original_premium')->label('Importo Premio Originario')
                //     ->columnSpan(3)
                //     // ->numeric()
                //     ->live(onBlur: true)
                //     ->extraInputAttributes(['class' => 'text-right'])
                //     ->afterStateUpdated(function ($state, $component) {
                //         $clean = preg_replace('/[^\d,\.-]/', '', $state);
                //         $number = str_replace(',', '.', $clean);
                //         $float = floatval($number);
                //         $formatted = number_format($float, 2, ',', '.');
                //         $component->state($formatted);
                //     })
                //     ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                //     ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                //     ->prefix('€')
                //     ->nullable(),
                // Forms\Components\Select::make('bail_status')->label('Stato Pagamento')
                //     ->columnSpan(3)
                //     ->options(\App\Enums\BailStatus::class)
                //     ->nullable(),
                // Forms\Components\DatePicker::make('original_pay_date')->label('In Data')
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->columnSpan(3)
                //     ->nullable(),
                // // Forms\Components\DatePicker::make('release_date')->label('Data Rilascio')
                // //     ->extraInputAttributes(['class' => 'text-center'])
                // //     ->columnSpan(3)
                // //     ->nullable(),
                // Forms\Components\Placeholder::make('')
                //     ->label('')
                //     ->content('')
                //     ->visible()
                //     ->columnspan(3),
                // Forms\Components\TextInput::make('renew_premium')->label('Importo Rinnovo')
                //     ->columnSpan(2)
                //     ->live(onBlur: true)
                //     ->extraInputAttributes(['class' => 'text-right'])
                //     ->afterStateUpdated(function ($state, $component) {
                //         $clean = preg_replace('/[^\d,\.-]/', '', $state);
                //         $number = str_replace(',', '.', $clean);
                //         $float = floatval($number);
                //         $formatted = number_format($float, 2, ',', '.');
                //         $component->state($formatted);
                //     })
                //     ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                //     ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
                //     // ->numeric()
                //     ->prefix('€')
                //     ->nullable(),
                // Forms\Components\DatePicker::make('renew_date')->label('Data Rinnovo')
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->columnSpan(2)
                //     ->nullable(),
                // Forms\Components\DatePicker::make('receipt_date')->label('Data Ricevuta')
                //     ->extraInputAttributes(['class' => 'text-center'])
                //     ->columnSpan(2)
                //     ->nullable(),

                // Forms\Components\FileUpload::make('receipt_attachment_path')->label('Allegato Ricevuta Pagamento')
                //     ->live()
                //     // ->disk('public')
                //     ->directory('bail/receipt-attachments')
                //     // ->visibility('public')
                //     ->getUploadedFileNameForStorageUsing(
                //         fn ($file, Get $get): string => Client::find($get('client_id'))->denomination . '_' . $get('bill_number') . '.' . $file->getClientOriginalExtension()
                //     )
                //     ->columnSpan(3)
                //     ->extraAttributes(['class' => 'file-upload-with-preview']),
                // Forms\Components\Actions::make([
                //     \Filament\Forms\Components\Actions\Action::make('view_receipt_attachment')
                //         ->label('Visualizza')
                //         ->icon('heroicon-o-eye')
                //         // ->url(fn($record): ?string => $record && $record->receipt_attachment_path ? Storage::url($record->receipt_attachment_path) : null)
                //         ->url(fn($record): ?string => $record && $record->receipt_attachment_path ? Storage::temporaryUrl($record->receipt_attachment_path,now()->addMinutes(1)) : null)
                //         ->openUrlInNewTab()
                //         ->hidden(fn ($record) => !$record || !$record->receipt_attachment_path),
                // ])
                // ->columnSpan(2),
                Forms\Components\Textarea::make('note')->label('Note')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('🔍 Cliente')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('cig_code')
                    ->label('🔍 CIG')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('tax_types') // MODIFICA: Rinominato da 'tax_type' a 'tax_types'
                    ->label('🔍 Tipo Entrata')
                    ->badge() // MODIFICA: Aggiunto per visualizzare come badge
                    ->color(fn (string $state): string => match ($state) { // MODIFICA: Aggiunto colori personalizzati
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
                    ->separator(', ') // MODIFICA: Aggiunto per separare valori multipli
                    ->searchable(query: function (Builder $query, string $search) { // MODIFICA: Aggiunto whereJsonContains per ricerca
                        $query->whereJsonContains('tax_types', $search);
                    }),
                Tables\Columns\TextColumn::make('insurance.name')
                    ->label('🔍 Compagnia')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bail_type')
                    ->label('🔍 Tipo')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
                Tables\Columns\TextColumn::make('bill_number')
                    ->label('🔍 Numero Polizza')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ?? 'N/A'),
                Tables\Columns\TextColumn::make('lastDetail.premium')
                    ->label('🔍 Premio')
                    ->searchable()
                    ->money('EUR', true, 'it_IT'),
                Tables\Columns\TextColumn::make('lastDetail.bill_deadline')
                    ->label('Scadenza Polizza')
                    ->date()
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/A'),
                Tables\Columns\TextColumn::make('remain_days')
                    ->label('Giorni rimanenti')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('bill_deadline', $direction))
                    ->getStateUsing(function ($record) {
                        $detail = $record->lastDetail;
                        if (!$detail || !$detail->bill_deadline) {
                            return 'N/A';
                        }
                        try {
                            $deadline = \Carbon\Carbon::parse($detail->bill_deadline);
                            $today = \Carbon\Carbon::today();
                            $daysRemaining = $today->diffInDays($deadline, false);
                            return $daysRemaining >= 0 ? $daysRemaining : ($detail->release_date ? 'Scaduta' : 'Scaduta e non svincolata');
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Errore nel calcolo dei giorni rimanenti: ' . $e->getMessage());
                            return 'Errore';
                        }
                    }),
                // Tables\Columns\TextColumn::make('bail_status')
                //     ->label('Stato Cauzione')
                //     ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
            ])
            ->filtersFormWidth('3xl')
            ->filtersFormColumns(6)
            ->filters([
                Tables\Filters\SelectFilter::make('bail_type')
                    ->options(\App\Enums\BailType::class)
                    ->multiple()
                    ->label('Tipo')
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            foreach ($data as $taxType) {
                                $query->whereIn('bail_type', $data['values']);
                            }
                        }
                    }),
                Tables\Filters\Filter::make('active_at_date') // Cambiato il nome per coerenza
                    ->columnSpan(2)
                    ->form([
                        Forms\Components\DatePicker::make('selected_date')
                            ->label('Attive al'),
                            // ->default(today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['selected_date'])) {
                            return $query;
                        }

                        return $query->whereHas('lastDetail', function (Builder $subQuery) use ($data) {
                            $subQuery->where('bill_deadline', '>=', $data['selected_date'])
                                ->orWhere(function ($q) use ($data) {
                                    $q->where('bill_deadline', '<', $data['selected_date'])
                                        ->whereNull('release_date');
                                });
                        });
                    }),
                Tables\Filters\SelectFilter::make('tax_types')
                    ->options(\App\Enums\TaxType::class)
                    ->multiple()
                    ->label('Entrata')
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values'])) {
                            foreach ($data as $taxType) {
                                $query->whereJsonContains('tax_types', $taxType);
                            }
                        }
                    }),
                // Tables\Filters\SelectFilter::make('insurance')
                //     ->options(function () {
                //         return \App\Models\Insurance::all()->pluck('name', 'id')->toArray();
                //     })
                //     ->label('Compagnia assicurativa')
                //     ->query(function ($query, $data) {
                //         if (!empty($data['value'])) {
                //             $query->where('insurance_id', $data);
                //         }
                //     }),
                Tables\Filters\Filter::make('insurance_agency')
                    ->columnSpan(6)
                    ->columns(2)
                    ->form([
                        // Campo Assicurazione
                        Forms\Components\Select::make('insurance_id')
                            ->label('Compagnia assicurativa')
                            ->options(\App\Models\Insurance::pluck('name', 'id'))
                            ->reactive() // Fondamentale per aggiornare l'altro campo
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('agency_id', null)), // Reset agenzia se cambio assicurazione

                        // Campo Agenzia
                        Forms\Components\Select::make('agency_id')
                            ->label('Agenzia')
                            ->options(function (Forms\Get $get) {
                                $insuranceId = $get('insurance_id');

                                if (!$insuranceId) {
                                    return \App\Models\Agency::pluck('name', 'id');
                                }

                                return \App\Models\Agency::where('insurance_id', $insuranceId)
                                    ->pluck('name', 'id');
                            })
                            ->disabled(fn (Forms\Get $get) => !$get('insurance_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['insurance_id'],
                                fn (Builder $query, $value): Builder => $query->where('insurance_id', $value),
                            )
                            ->when(
                                $data['agency_id'],
                                fn (Builder $query, $value): Builder => $query->where('agency_id', $value),
                            );
                    }),
                // Tables\Filters\SelectFilter::make('bail_status')
                //     ->options(\App\Enums\BailStatus::class)
                //     ->label('Stato')
                //     ->query(function (Builder $query, array $data) {
                //         if (!empty($data['value'])) {
                //             $query->where('bail_status', $data['value']);
                //         }
                //     }),
                Tables\Filters\SelectFilter::make('bail_status')
                    ->options(\App\Enums\BailStatus::class)
                    ->label('Stato')
                    ->columnSpan(3)
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            // Utilizziamo doveHas per entrare nella relazione lastDetail
                            $query->whereHas('lastDetail', function (Builder $subQuery) use ($data) {
                                $subQuery->where('bail_status', $data['value']);
                            });
                        }
                    }),
                // Tables\Filters\SelectFilter::make('expiration_status')
                //     ->label('Stato Scadenza')
                //     ->options([
                //         '' => 'Tutti',
                //         'expired' => 'Scaduti',
                //         'expired_not_paid' => 'Scaduti e non pagati',
                //         'expired_not_released' => 'Scaduti e non svincolati',
                //     ])
                //     ->query(function (Builder $query, array $data): Builder {
                //         return match ($data['value']) {
                //             'expired' => $query->where('bill_deadline', '<', now()),
                //             'expired_not_paid' => $query->where('bill_deadline', '<', now())->whereNull('original_pay_date'),
                //             'expired_not_released' => $query->where('bill_deadline', '<', now())->whereNull('release_date'),
                //             default => $query,
                //         };
                //     }),
                Tables\Filters\SelectFilter::make('expiration_status')
                    ->label('Stato Scadenza')
                    ->columnSpan(3)
                    ->options([
                        'expired' => 'Scadute',
                        'expired_not_paid' => 'Scadute e non pagate',
                        'expired_not_released' => 'Scadute e non svincolate',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('lastDetail', function (Builder $subQuery) use ($data) {
                            $now = now();

                            return match ($data['value']) {
                                'expired' => $subQuery->where('bill_deadline', '<', $now),

                                'expired_not_paid' => $subQuery->where('bill_deadline', '<', $now)
                                    ->where(function($q) {
                                        // Verifica che non sia pagato (controlla il tuo enum o la data)
                                        $q->whereNull('pay_date')
                                        ->orWhere('bail_status', '!=', 'payed');
                                    }),

                                'expired_not_released' => $subQuery->where('bill_deadline', '<', $now)
                                    ->whereNull('release_date'), // Assicurati che release_date sia in bail_details

                                default => $subQuery,
                            };
                        });
                    }),
                // Tables\Filters\Filter::make('not_paid')
                //     ->form([
                //         Forms\Components\Checkbox::make('not_paid')
                //             ->label('Senza data di pagamento'),
                //     ])
                //     ->query(function (Builder $query, array $data): Builder {
                //         return $data['not_paid'] ? $query->whereNull('original_pay_date') : $query;
                //     }),
                Tables\Filters\Filter::make('not_paid')
                    ->form([
                        Forms\Components\Checkbox::make('not_paid')
                            ->label('Senza data di pagamento'),
                    ])
                    ->columnSpan(3)
                    ->query(function (Builder $query, array $data): Builder {
                        // Se la checkbox non è spuntata, non applichiamo filtri
                        if (!$data['not_paid']) {
                            return $query;
                        }

                        // Entriamo nella relazione lastDetail
                        return $query->whereHas('lastDetail', function (Builder $subQuery) {
                            $subQuery->whereNull('pay_date')
                                        ->where('bail_status', '!=', \App\Enums\BailStatus::PAYED);
                        });
                    }),
                // Tables\Filters\Filter::make('not_receipt')
                //     ->form([
                //         Forms\Components\Checkbox::make('not_receipt')
                //             ->label('Senza allegato pagamento'),
                //     ])
                //     ->query(function (Builder $query, array $data): Builder {
                //         return $data['not_receipt'] ? $query->whereNull('receipt_attachment_path') : $query;
                //     }),
                Tables\Filters\Filter::make('not_receipt')
                    ->form([
                        Forms\Components\Checkbox::make('not_receipt')
                            ->label('Senza allegato quietanza'),
                    ])
                    ->columnSpan(3)
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['not_receipt']) {
                            return $query;
                        }

                        return $query->whereHas('lastDetail', function (Builder $subQuery) {
                            $subQuery->whereNull('attachment_path')
                                    ->orWhere('attachment_path', '');
                        });
                    }),
            // ], layout: FiltersLayout::Modal)->filtersFormColumns(3)
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
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
            BailDetailsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBails::route('/'),
            'create' => Pages\CreateBail::route('/create'),
            'edit' => Pages\EditBail::route('/{record}/edit'),
            'view' => Pages\ViewBail::route('/{record}'),
        ];
    }

    public static function saveClient(array $data, Client $client, Get $get, Set $set): void
    {
        // dd($data);
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

    public static function saveInsurance(array $data, Insurance $insurance, Get $get, Set $set): void
    {
        // dd($data);
        $insurance->company_id = Filament::getTenant()->id;
        $insurance->name = $data['name'] ?? null;
        $insurance->description = $data['description'] ?? null;
        $insurance->save();

        $set('insurance_id', $insurance->id);

        Notification::make()
            ->title('Compagnia assicurativa salvata con successo')
            ->success()
            ->send();
    }

    public static function saveAgency(array $data, Agency $agency, Get $get, Set $set): void
    {
        // dd($data);
        $agency->company_id = Filament::getTenant()->id;
        $agency->insurance_id = $data['insurance_id'] ?? null;
        $agency->name = $data['name'] ?? null;
        $agency->description = $data['description'] ?? null;
        $agency->save();

        $set('agency_id', $agency->id);

        Notification::make()
            ->title('Agenzia salvata con successo')
            ->success()
            ->send();
    }
}
