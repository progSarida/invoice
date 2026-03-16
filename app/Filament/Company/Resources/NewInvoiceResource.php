<?php

namespace App\Filament\Company\Resources;

use App\Enums\ClientSubType;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Tables;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\Tender;
use App\Models\DocType;
use App\Models\Invoice;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Enums\SdiStatus;
use Filament\Forms\Form;
use App\Enums\ClientType;
use App\Enums\InvoiceReference;
use App\Enums\TimingType;
use App\Models\Sectional;
use App\Enums\InvoiceType;
use App\Enums\InvoicingCicle;
use App\Enums\PaymentMode;
use App\Enums\PaymentType;
use App\Models\ManageType;
use App\Models\NewInvoice;
use Filament\Tables\Table;
use App\Models\AccrualType;
use App\Models\NewContract;
use App\Enums\PaymentStatus;
use App\Enums\ReversalGroupType;
use App\Enums\VatEnforceType;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Grid;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Company\Resources\NewInvoiceResource\Pages;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\CreditNotesRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\InvoiceItemsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\ActivePaymentsRelationManager;
use App\Filament\Company\Resources\NewInvoiceResource\RelationManagers\SdiNotificationsRelationManager;
use App\Models\ReversalMotivationType;
use App\Models\SocialContribution;
use App\Models\Withholding;
use Exception;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Storage;

class NewInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static ?string $pluralModelLabel = 'Fatture';

    public static ?string $modelLabel = 'Fattura';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione attiva';

    protected static ?int $navigationSort = 1;

    protected static ?int $navigationGroupSort = 2;

    public static function form(Form $form): Form
    {
        // 1. Definiamo la subquery per trovare l'ultima data di dettaglio per ogni contratto
        // Lo facciamo fuori dalle closure principali per riutilizzarlo e per chiarezza
        $latestDetailSubquery = \App\Models\ContractDetail::query()
            ->selectRaw('contract_id, MAX(date) as latest_detail_date')
            ->groupBy('contract_id')
            ->toBase();

        return $form
            ->schema([
                // Grid::make('GRID')->columnSpan(2)->schema([

                    Section::make('Opzioni')
                    // ->collapsible()
                    ->columns(12)
                    ->collapsed()
                    ->label('')
                    ->schema([
                        Toggle::make('art_73')
                            ->label('Art. 73')
                            ->dehydrated()
                            ->columnSpan(2)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                if ($state) {
                                    $set('sectional_id', null);
                                    $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                    $set('number', $number);
                                    NewInvoiceResource::invoiceNumber($get, $set);
                                }
                                else{
                                    $clientId = $get('client_id');
                                    if ($clientId) {
                                        $client = \App\Models\Client::find($clientId);
                                        if ($client && $client->type) {
                                            $sectional = \App\Models\Sectional::where('company_id', Filament::getTenant()->id)
                                                ->where('client_type', $client->type->value)
                                                ->first();
                                            if ($sectional) {
                                                $set('sectional_id', $sectional->id);
                                                $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                                $set('number', $number);
                                                NewInvoiceResource::invoiceNumber($get, $set);
                                            } else {
                                                $set('sectional_id', null);
                                                $set('number', null);
                                                NewInvoiceResource::invoiceNumber($get, $set);
                                                Notification::make()
                                                    ->title('Nessun sezionario trovato per il tipo di cliente selezionato.')
                                                    ->warning()
                                                    ->send();
                                            }
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Select::make('social_contributions')
                            ->label('')
                            // ->columnSpan(4)
                            ->columnSpan(6)
                            ->placeholder('Cassa previdenziale')
                            ->multiple()
                            ->options(function () {
                                return SocialContribution::where('company_id', Filament::getTenant()->id)
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [$item->id => $item->fund->getLabel()])
                                    ->toArray();
                            })
                            // ->dehydrated(fn ($state) => is_array($state) && count($state)),
                            ->dehydrated(),

                        Forms\Components\Select::make('withholdings')
                            ->label('')
                            // ->columnSpan(3)
                            ->columnSpan(4)
                            ->placeholder('Ritenute')
                            ->multiple()
                            ->options(function () {
                                return Withholding::where('company_id', Filament::getTenant()->id)
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [$item->id => $item->withholding_type->getLabel()])
                                    ->toArray();
                            })
                            // ->dehydrated(fn ($state) => is_array($state) && count($state)),
                            ->dehydrated(),

                        ]),

                    Section::make('Destinatario')
                        ->collapsible()
                        ->columns(6)
                        ->schema([
                            Forms\Components\Select::make('client_id')->label('Cliente')
                                ->hintAction(
                                    Action::make('Nuovo')
                                        ->icon('ri-user-2-line')
                                        ->form(fn (Form $form) => ClientResource::modalForm($form))
                                        ->modalHeading('')
                                        ->modalWidth('7xl')
                                        ->action(fn (array $data, Client $client, Get $get, Set $set) => NewInvoiceResource::saveClient($data, $client, $get, $set))
                                )
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
                                    // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                    fn (Model $record) => $record->denomination
                                )
                                ->required()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $set('contract_id', null);
                                    $set('sectional_id', null);
                                    $set('tax_type', null);
                                    $clientId = $get('client_id');
                                    $art73 = $get('art_73');
                                    if ($clientId && !$art73) {
                                        $client = \App\Models\Client::find($clientId);
                                        if ($client && $client->type) {
                                            $sectional = \App\Models\Sectional::where('company_id', Filament::getTenant()->id)
                                                ->where('client_type', $client->type->value)
                                                ->first();
                                            if ($sectional) {
                                                $set('sectional_id', $sectional->id);
                                                $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                                                $set('number', $number);
                                                NewInvoiceResource::invoiceNumber($get, $set);
                                            } else {
                                                $set('sectional_id', null);
                                                $set('number', null);
                                                NewInvoiceResource::invoiceNumber($get, $set);
                                                Notification::make()
                                                    ->title('Nessun sezionario trovato per il tipo di cliente selezionato.')
                                                    ->warning()
                                                    ->send();
                                            }
                                        }
                                    }
                                })
                                ->searchable()
                                ->live()
                                ->preload()
                                ->optionsLimit(5)
                                ->columnSpan(4),

                            Forms\Components\Select::make('tax_type')->label('Entrata')
                                ->required(fn(Get $get): bool => filled($get('client_id')) && Client::find($get('client_id'))->isPublic())
                                ->columnSpan(2)
                                // ->options(TaxType::class)
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
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    if(empty($get('client_id')) || empty($get('tax_type')))
                                    $set('contract_id', null);
                                })
                                ->searchable()
                                ->live()
                                ->preload()
                                ->visible(fn(Get $get): bool => filled($get('client_id')))
                                ->hidden(function (?Model $record = null) {
                                    // In edit, usa il record
                                    if ($record) { return $record->tax_type == TaxType::EMPTY; }
                                    // In create, usa il valore del form
                                    return false;
                                }),

                            Forms\Components\Select::make('contract_id')->label('Contratto')
                                // ->relationship(
                                //     name: 'contract',
                                //     modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                //         ->where('client_id', $get('client_id'))
                                //         ->whereJsonContains('tax_types', $get('tax_type'))
                                //         ->where('end_validity_date', '>=', today())
                                //         ->where('start_validity_date', '<=', today())
                                // )
                                ->relationship(
                                    name: 'contract',
                                    modifyQueryUsing: function (Builder $query, Get $get, $livewire) use ($latestDetailSubquery) {
                                        $query->where('client_id', $get('client_id'))
                                            ->whereJsonContains('tax_types', $get('tax_type'));

                                        // *** MODIFICA CHIAVE ***: Usiamo la subquery per ottenere SOLO l'ultima data
                                        $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                            $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                                        });

                                        // applico i filtri di validità solo in fase di creazione
                                        if ($livewire instanceof \Filament\Resources\Pages\CreateRecord) {
                                            // $query->where(function ($q) {
                                            //     $q->whereNull('start_validity_date')
                                            //     ->orWhere(function ($q2) {
                                            //         $q2->where('start_validity_date', '<=', today())
                                            //             ->where('end_validity_date', '>=', today());
                                            //     });
                                            // });
                                            $query->where('closed', false);
                                        }

                                        // 1. Seleziona tutte le colonne necessarie e aggiungi l'anno calcolato
                                        $query->select('new_contracts.*')
                                            ->selectRaw('
                                                COALESCE(
                                                    YEAR(new_contracts.start_validity_date),
                                                    YEAR(latest_details.latest_detail_date)
                                                ) AS calculated_year' // Ora usa latest_details.latest_detail_date
                                            )
                                            ->distinct();

                                        // 2. Ordina per l'anno calcolato (Ultimo Anno per Primo)
                                        $query->orderByRaw('calculated_year DESC');

                                        return $query;
                                    },
                                )
                                ->getSearchResultsUsing(function (string $search, Get $get) use ($latestDetailSubquery) {
                                    // 1. Pulisci la stringa di ricerca
                                    $search = trim(preg_replace('/\s+/', ' ', $search));

                                    // 2. Query di base e JOIN (Replicata da modifyQueryUsing)
                                    $query = \App\Models\NewContract::query();

                                    // *** MODIFICA CHIAVE ***: Usiamo la subquery per ottenere SOLO l'ultima data
                                    $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                                        $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                                    });


                                    // Aggiungi i filtri di base del form (necessario per limitare i risultati)
                                    $query->where('client_id', $get('client_id'))
                                        ->whereJsonContains('tax_types', $get('tax_type'));

                                    // 3. Applicazione del Filtro di Ricerca
                                    $query->where(function ($q) use ($search) {

                                        // Cerca per CIG CODE
                                        $q->where('new_contracts.cig_code', 'LIKE', "%{$search}%");

                                        // Cerca per ANNO CALCOLATO (se il search è un numero)
                                        if (is_numeric($search)) {
                                            // Usiamo lo stesso COALESCE che definisce calculated_year per la ricerca
                                            $q->orWhereRaw('
                                                YEAR(COALESCE(
                                                    new_contracts.start_validity_date,
                                                    latest_details.latest_detail_date
                                                )) = ?', [$search]
                                            );
                                        }
                                    });

                                    // 4. Selezione delle colonne e Ordinamento (Replicata da modifyQueryUsing)
                                    $query->select('new_contracts.*')
                                        ->selectRaw('
                                            COALESCE(
                                                YEAR(new_contracts.start_validity_date),
                                                YEAR(latest_details.latest_detail_date)
                                            ) AS calculated_year' // Ora usa latest_details.latest_detail_date
                                        )
                                        ->distinct();

                                    // 5. Ordinamento corretto: Torniamo a DESC + Tie-breaker
                                    // Dopo aver stabilizzato il calculated_year, l'ordinamento DESC dovrebbe funzionare.
                                    $query->orderByRaw('calculated_year DESC')
                                        ->orderBy('new_contracts.id', 'DESC'); // Tie-breaker

                                    // 6. Esecuzione e Mappatura dei risultati (Formato armonizzato)
                                    return $query
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($record) {
                                            // Usa l'attributo calcolato per l'etichetta nel formato desiderato
                                            $label = "{$record->office_name} ({$record->office_code}) TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}";
                                            return [$record->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelFromRecordUsing(
                                    // fn (Model $record) => "{$record->office_name} ({$record->office_code}) TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}"
                                    function(Model $record, $livewire){
                                        if ($livewire instanceof \Filament\Resources\Pages\CreateRecord) {
                                            $accruals = '';
                                            $manages = '';
                                            for($i = 0; $i < count($record->accrual_types); $i++){
                                                if($i != 0)
                                                    $accruals .= ', ';
                                                $accruals .= $record->accrual_types[$i];
                                            }
                                            for($j = 0; $j < count($record->manage_types); $j++){
                                                if($j != 0)
                                                    $manages .= ', ';
                                                $manages .= ManageType::find($record->manage_types[$j])->name;
                                            }
                                            return $record->calculated_year . ' - ' . 'TIPO: ' . $record->payment_type->getLabel() . ' - ' . 'GESTIONI: ' . $accruals . ' - ' . 'SERVIZI: ' . $manages;
                                        }
                                        else {
                                            // 1. Fallback per l'anno: in Edit/View 'calculated_year' non esiste nella query standard
                                            $year = $record->start_validity_date ? $record->start_validity_date->format('Y') : '----';

                                            // 2. Gestione Accruals (Gestioni)
                                            // Il tuo accessor getAccrualTypesAttribute restituisce già i nomi degli AccrualType.
                                            // Basta unirli con una virgola.
                                            $accrualList = $record->accrual_types ?? [];
                                            $accruals = is_array($accrualList) ? implode(', ', $accrualList) : '';

                                            // 3. Gestione Manages (Servizi)
                                            // manage_types è castato come 'json', quindi Laravel lo trasforma in array.
                                            $manageIds = $record->manage_types ?? [];
                                            $manages = '';

                                            if (is_array($manageIds) && count($manageIds) > 0) {
                                                $manages = collect($manageIds)
                                                    ->map(fn($id) => \App\Models\ManageType::find($id)?->name ?? "ID: {$id}")
                                                    ->filter()
                                                    ->implode(', ');
                                            }

                                            // 4. Composizione finale
                                            $paymentTypeLabel = $record->payment_type ? $record->payment_type->getLabel() : 'N/D';

                                            return "{$year} - TIPO: {$paymentTypeLabel} - GESTIONI: " . ($accruals ?: 'Nessuna') . " - SERVIZI: " . ($manages ?: 'Nessuno');
                                        }

                                    }
                                )
                                ->disabled(fn(Get $get): bool => !filled($get('client_id')) && !filled($get('tax_type')))
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    if($state) {
                                        $contract = NewContract::find($state);
                                        $lastDetail = $contract->lastDetail()->first();
                                        if (!$lastDetail) {
                                            // $set('contract_id', null);
                                            Notification::make()
                                                ->title('Attenzione! E\' stato selezionato un contratto senza dettagli.')
                                                ->warning()
                                                ->duration(6000)
                                                ->actions([
                                                        \Filament\Notifications\Actions\Action::make('edit')
                                                            ->label('Vai al contratto')
                                                            ->url(NewContractResource::getUrl('edit', ['record' => $contract->id]))
                                                            ->openUrlInNewTab()
                                                            ->color('warning'),
                                                    ])
                                                ->send();
                                        }
                                        $set('contract_detail_id', null);
                                    }
                                })
                                ->required(fn(Get $get): bool => filled($get('client_id')) && Client::find($get('client_id'))->isPublic())
                                ->searchable()
                                ->live()
                                ->preload()
                                ->optionsLimit(5)
                                ->columnSpan(4)
                                ->visible(fn(Get $get): bool => filled($get('client_id')))
                                ->hidden(function (?Model $record = null) {
                                    // In edit, usa il record
                                    if ($record) { return !$record->contract_id; }
                                    // In create, usa il valore del form
                                    return false;
                                })
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    if ($state) {
                                        $contract = \App\Models\NewContract::find($state);
                                        // $set('accrual_type_id', $contract ? $contract->accrual_type_id : null);
                                    } else {
                                        $set('accrual_type_id', null);
                                    }
                                })
                                ->hintAction(
                                    Action::make('Nuovo')
                                        ->icon('tabler-contract')
                                        ->visible(fn(Get $get): bool => filled($get('tax_type')))
                                        ->fillForm(fn (Get $get): array => [
                                            'client_id' => $get('client_id'),
                                            'tax_type' => $get('tax_type'),
                                        ])
                                        ->form( fn(Form $form) => NewContractResource::modalForm($form) )
                                        ->modalWidth('7xl')
                                        ->modalHeading('')
                                        // ->action( fn(array $data, NewContract $contract, Set $set) => NewContractResource::saveContract($data, $contract, $set) )
                                        ->action(function (array $data, NewContract $contract, Set $set) {
                                            NewContractResource::saveContract($data, $contract, $set);

                                            $lastDetail = $contract->lastDetail()->first();

                                            if (!$lastDetail) {
                                                Notification::make()
                                                    ->title('Attenzione! Il contratto creato è senza dettagli.')
                                                    ->warning()
                                                    ->duration(6000)
                                                    ->actions([
                                                        \Filament\Notifications\Actions\Action::make('edit')
                                                            ->label('Vai al contratto')
                                                            ->url(NewContractResource::getUrl('edit', ['record' => $contract->id]))
                                                            ->openUrlInNewTab()
                                                            ->color('warning'),
                                                    ])
                                                    ->send();
                                            }
                                        })
                                ),

                            Forms\Components\Select::make('contract_detail_id')
                                ->label('Dettaglio Contratto')
                                // ->required()
                                ->placeholder('Seleziona prima un contratto')
                                ->visible(fn(Get $get): bool => filled($get('client_id')))
                                ->hidden(function (?Model $record = null) {
                                    // In edit, usa il record
                                    if ($record) { return !$record->contract_id; }
                                    // In create, usa il valore del form
                                    return false;
                                })
                                // 1. Rende il campo dinamico in base allo stato del form
                                ->options(function (Get $get) {
                                    $contractId = $get('contract_id');

                                    // Se non c'è un contratto selezionato, restituiamo un array vuoto
                                    if (! $contractId) {
                                        return [];
                                    }

                                    // Recuperiamo i dettagli associati al contratto selezionato
                                    return \App\Models\ContractDetail::where('contract_id', $contractId)
                                        ->orderBy('date', 'desc')
                                        ->get()
                                        ->mapWithKeys(function ($detail) {
                                            // Definiamo l'etichetta (es: "Num. 123 del 01/01/2024 - Descrizione")
                                            $date = $detail->date ? $detail->date->format('d/m/Y') : 'Data N/D';
                                            $label = "{$detail->contract_type?->getLabel()} n. {$detail->number} del {$date}";

                                            return [$detail->id => $label];
                                        })
                                        ->toArray();
                                })
                                // Importante: permette al campo di ricaricarsi quando contract_id cambia
                                ->live()
                                // Opzionale: disabilita il campo se il contratto non è ancora scelto
                                ->disabled(fn (Get $get) => ! filled($get('contract_id')))
                                ->columnSpan(2),
                        ]),

                // ]),
                // Grid::make('GRID')->columnSpan(3)->schema([

                    Section::make('')
                        ->columns(6)
                        ->schema([
                            Forms\Components\Select::make('timing_type')->label('Modalità di fatturazione')->options(TimingType::class)
                                ->required(fn (Get $get) => $get('timing_type') == 'differita')
                                ->placeholder(null)
                                ->default('contestuale')
                                ->live()
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('delivery_note')->label('Documento di trasporto')
                                ->required(fn (Get $get) => $get('timing_type') == 'differita')
                                ->columnSpan(2)->disabled(fn (Get $get) => $get('timing_type') != 'differita'),
                            Forms\Components\DatePicker::make('delivery_date')->label('Data documento')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->required(fn (Get $get) => $get('timing_type') == 'differita')
                                ->columnSpan(2)->disabled(fn (Get $get) => $get('timing_type') != 'differita')
                                ->native(false)
                                ->displayFormat('d F Y'),
                        ]),

                    Section::make('')
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
                                    static::updateDescription($get, $set, 'new_doc');
                                })
                                ->options(function (Get $get) {
                                    $sectionalId = $get('sectional_id');
                                    $art73 = $get('art_73');
                                    if ($art73) {
                                        // $docs = DocType::get();
                                        // $docs = \Filament\Facades\Filament::getTenant()->docTypes();
                                        $docs = \Filament\Facades\Filament::getTenant()
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
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
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
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
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
                                        \Filament\Notifications\Notification::make()
                                            ->title('')
                                            ->body('E\' passato più di un anno dall\'emissione della fattura da stornare<br>Gestire limite temporale ed eventuale motivazione per emettere la nota di credito')
                                            ->warning()
                                            ->duration(10000)
                                            ->send();
                                    $accepted = $parent->sdi_status == SdiStatus::ACCETTATA->value;
                                    $note = DocType::find($get('doc_type_id'))->description == 'Nota di credito';
                                    if($accepted && $note )
                                        \Filament\Notifications\Notification::make()
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
                                    static::updateDescription($get, $set, 'continue');
                                })
                                ->required(function (?Model $record, Get $get) {
                                    // $privateR = ($record && $record->client->type->isPrivate() ? true : false);
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
                                        if($record->client->type->isPublic())
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
                                //     return $record && $record->client->type->isPublic() ? true : false;
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
                                    $query = \App\Models\Sectional::where('company_id', Filament::getTenant()->id);
                                    $clientId = $get('client_id');
                                    if ($clientId) {
                                        $client = \App\Models\Client::find($clientId);
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
                                ->afterStateUpdated(function (Get $get, Set $set, $state, ?Invoice $record) {
                                    if (!$state || !$get('number') || !$get('sectional_id') || !$get('year')) return;
                                    $year = $get('year');
                                    $date = \Illuminate\Support\Carbon::parse($state);

                                    if ($date->format('Y') != $year){
                                        \Filament\Notifications\Notification::make()
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
                                        ->first();

                                    if ($inconsistentInvoicePrec) {
                                        \Filament\Notifications\Notification::make()
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
                                        ->first();

                                    if ($inconsistentInvoiceSucc) {
                                        \Filament\Notifications\Notification::make()
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
                                ->minValue(now()->subYears(11)->year)
                                ->maxValue(now()->year)
                                ->default(now()->year)
                                ->rules(['digits:4'])
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('accrual_year')->label('Anno di competenza')
                                ->numeric()
                                ->required()
                                ->extraInputAttributes(['class' => 'text-right'])
                                ->minValue(now()->subYears(11)->year)
                                ->maxValue(now()->year)
                                ->default(now()->year)
                                ->rules(['digits:4'])
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
                                ->required()
                                ->live()
                                ->options(InvoiceReference::class)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'new_ref'))
                                ->preload()
                                ->columnSpan(2),

                            Forms\Components\DatePicker::make('reference_date_from')
                                ->label('Da data')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->required()
                                // ->live()
                                ->debounce(1000)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
                                ->visible(fn (Get $get): bool => $get('invoice_reference') !== InvoiceReference::NUMBER->value)
                                ->columnSpan(2),

                            Forms\Components\DatePicker::make('reference_date_to')
                                ->label('A data')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->required()
                                // ->live()
                                ->debounce(1000)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
                                ->visible(fn (Get $get): bool => $get('invoice_reference') !== InvoiceReference::NUMBER->value)
                                ->columnSpan(2),
                            Placeholder::make('')
                                ->content('')
                                ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('reference_number_from')->label('Dal numero')
                                ->required()
                                ->debounce(500)
                                ->extraInputAttributes(['class' => 'text-right'])
                                ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('reference_number_to')->label('Al numero')
                                ->required()
                                ->debounce(500)
                                ->extraInputAttributes(['class' => 'text-right'])
                                ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('total_number')->label('Totali')
                                ->required()
                                ->debounce(500)
                                ->extraInputAttributes(['class' => 'text-right'])
                                ->visible(fn (Get $get): bool => $get('invoice_reference') === InvoiceReference::NUMBER->value)
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::updateDescription($get, $set, 'continue'))
                                ->columnSpan(1),
                        ]),

                    Section::make('Descrizioni')
                        ->collapsible()
                        ->schema([
                            // Forms\Components\Textarea::make('description')->label("Descrizione (Composizione automatica 'variabile dall'operatore', con inserimento dei campi 'Anno di bilancio', 'Descrizione da riportare in fattura' (presente nel dettaglio del contratto), Riferimento, 'Da data' e 'A data', oppure 'Dal numero', 'Al numero' e 'Totali')")
                            Forms\Components\Textarea::make('description')->label('Descrizione')
                                ->required()
                                ->live()
                                ->hintIcon('heroicon-o-information-circle', tooltip: "Composizione automatica 'variabile dall'operatore', con inserimento dei campi 'Anno di bilancio', 'Descrizione da riportare in fattura' (presente nel dettaglio del contratto), Riferimento, 'Da data' e 'A data', oppure 'Dal numero', 'Al numero' e 'Totali'")
                                ->afterStateUpdated(function ($state) {
                                    if (! preg_match('/\(ab\d{2}\)/', $state)) {
                                        \Filament\Notifications\Notification::make()
                                            ->title("Errore! La descrizione deve contenere il riferimento all'anno di bilancio nel formato (ab**)")
                                            ->danger()
                                            ->persistent()
                                            ->send();
                                    }
                                })
                                ->rules([
                                    fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                        if (! preg_match('/\(ab\d{2}\)/', $value)) {
                                            $fail("La descrizione deve contenere i riferimenti all'anno di bilancio nel formato (ab**)");
                                        }
                                    },
                                ])
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('free_description')->label('Descrizione libera')
                                // ->required()
                                ->columnSpanFull(),
                        ]),

                    Section::make('Dati per il pagamento')->columns(4)
                        ->collapsed(false)
                        ->columns(12)
                        ->schema([
                            Forms\Components\Select::make('bank_account_id')->label('IBAN')
                                ->relationship(
                                    name: 'bankAccount',
                                    modifyQueryUsing: fn (Builder $query) =>
                                    $query->where('company_id',Filament::getTenant()->id)->orderBy('position', 'asc')
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (Model $record) => "{$record->name} $record->iban"
                                )
                                ->searchable()
                                ->required()
                                ->columnSpan(5)
                                ->preload(),
                            Forms\Components\Select::make('payment_mode')->label('Modalità')
                                // ->options(PaymentType::class)
                                ->afterStateUpdated( function(Set $set, $state){
                                    if($state == PaymentMode::TP02->value){
                                        $set('rate_number', 1);
                                    }
                                    else{
                                        $set('rate_number', null);
                                    }
                                })
                                ->reactive()
                                ->options(
                                    collect(PaymentMode::cases())
                                        ->sortBy(fn (PaymentMode $type) => $type->getOrder())
                                        ->mapWithKeys(fn (PaymentMode $type) => [
                                            $type->value => $type->getLabel()
                                        ])
                                        ->toArray()
                                )
                                ->required()
                                ->default(PaymentMode::TP02->value)
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('rate_number')
                                ->label('Rate')
                                ->extraInputAttributes(['class' => 'text-right'])
                                ->columnSpan(1)
                                ->default(1)
                                ->required(fn(Get $get): bool => $get('payment_mode') != PaymentMode::TP02->value)
                                ->disabled(fn(Get $get): bool => $get('payment_mode') == PaymentMode::TP02->value)
                                ->dehydrated(),
                            Forms\Components\Select::make('payment_type')->label('Tipo')
                                // ->options(PaymentType::class)
                                ->options(
                                    collect(PaymentType::cases())
                                        ->sortBy(fn (PaymentType $type) => $type->getOrder())
                                        ->mapWithKeys(fn (PaymentType $type) => [
                                            $type->value => $type->getLabel()
                                        ])
                                        ->toArray()
                                )
                                ->required()
                                ->default('mp05')
                                ->columnSpan(3),
                            Forms\Components\Select::make('payment_days')
                                ->label('Giorni')
                                ->required()
                                ->options([
                                    30 => '30',
                                    60 => '60',
                                    90 => '90',
                                    120 => '120',
                                ])
                                ->default(30)
                                ->columnSpan(1),
                                ]),

                        Section::make('Stato SDI')->columns(2)
                            ->collapsed()
                            ->columns(6)
                            ->schema([
                                Forms\Components\Select::make('sdi_status')->label('Ultimo stato')->options(SdiStatus::class)
                                    ->default(SdiStatus::DA_INVIARE)
                                    // ->disabled(fn ($state) => !in_array($state, ['rifiutata', 'scartata', 'mancata_consegna']))
                                    ->disabled(fn ($state) => match(true) {
                                        $state instanceof SdiStatus => $state->lockChange(),
                                        $state !== null => SdiStatus::tryFrom($state)?->lockChange() ?? true,
                                        default => true,
                                    })
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('sdi_code')->label('Codice SdI')->readOnly()->columnSpan(2)->disabled(),
                                Forms\Components\DatePicker::make('sdi_date')->label('Data')
                                    ->extraInputAttributes(['class' => 'text-center'])->readOnly()->columnSpan(2)->disabled()
                                    ->native(false)
                                    ->displayFormat('d F Y'),
                            ]),

                        Section::make('Stato del pagamento')->columns(2)
                            ->collapsed()
                            ->columns(6)
                            ->schema([
                                Forms\Components\Select::make('payment_status')->label('Status')
                                    ->required()
                                    ->default('waiting')
                                    ->options(PaymentStatus::class)->columnSpan(2),

                                Forms\Components\DatePicker::make('last_payment_date')->label('Data ultimo pagamento')
                                    ->extraInputAttributes(['class' => 'text-center'])
                                    ->native(false)
                                    ->displayFormat('d F Y')->columnSpan(2)->disabled(),
                                Forms\Components\TextInput::make('total_payment')->label('Totale pagamenti')
                                    ->extraInputAttributes(['style' => 'text-align: right;'])
                                    ->columnSpan(2)
                                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                                    // ->numeric()
                                    ->suffix('€')->columnSpan(1)->disabled(),

                            ]),

                // ]),//FIRST GRID



            // ])->columns(5);
            ]);

    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Invoice::newInvoices())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Id')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('docType.description')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')->label('Numero')
                    ->formatStateUsing(function ( Invoice $invoice) {
                        return $invoice->getNewInvoiceNumber();
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('year', $direction)
                            ->orderBy('sectional_id', $direction)
                            ->orderBy('number', $direction);
                    }),
                Tables\Columns\TextColumn::make('invoice_date')->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('description')->label('Descrizione')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    // ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('invoice.id')->label('Fattura stornata')
                    ->formatStateUsing(function ( string $state ) {
                        $invoice = Invoice::find($state);
                        return $invoice->getNewInvoiceNumber();
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('parent_id')->label('Id fattura stornata')
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract.cig_code')->label('CIG')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract.cup_code')->label('CUP')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract.rdo_code')->label('RDO')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tax_type')->label('Entrata')
                    ->searchable()
                    // ->badge()
                    ->color('black')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('no_vat_total')->label('Imponibile')
                    ->money('EUR')
                    ->sortable()
                    ->state(fn (Invoice $invoice) => $invoice->getTaxable())
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('vat')->label('IVA')
                    ->money('EUR')
                    // ->state(fn (Invoice $invoice) => $invoice->getVat())
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total')->label('Totale')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    // ->tooltip(fn (Invoice $record) => $record->total . " - " . "(" . $record->total_payment . " + " . $record->total_notes . ")" . " = " . $record->total-($record->total_payment+$record->total_notes))
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total_payment')->label('Pagamenti')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('total_notes')->label('Note di credito')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('tot_res')->label('Dovuto')
                    ->money('EUR')
                    ->state(fn (Invoice $invoice) => $invoice->parent_id ? 0.00 : $invoice->getResidue())
                    ->sortable()
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false),
                // Tables\Columns\TextColumn::make('sdi_status')->label('Stato')
                //     ->searchable()
                //     // ->badge()
                //     ->color('black')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('sdi_status')->label('Stato')
                    ->tooltip(fn (SdiStatus $state): string => $state->getLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('sdi_date')->label('Data status')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('doc_type_id')
                    ->label('Seleziona tipo documento')
                    // ->options(function () {
                    //     return DocType::orderBy('doc_group_id')->pluck('description', 'id')->toArray();
                    // })
                    ->options(function (Get $record) {
                        $docs = \Filament\Facades\Filament::getTenant()
                                    ->docTypes()
                                    ->select('doc_types.id', 'doc_types.description')
                                    ->get();
                        return $docs ? $docs->pluck('description', 'id')->toArray() : [];
                    })
                    ->multiple()
                    ->searchable()
                    ->columnSpan(2)
                    ->preload(),
                Tables\Filters\SelectFilter::make('exclude_doc_types')
                    ->label('Escludi tipo documento')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2)
                    // 1. Carichiamo le opzioni dal Tenant
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if (!$tenant) return [];

                        return $tenant->docTypes()
                            ->pluck('description', 'doc_types.id')
                            ->toArray();
                    })
                    // 2. Impostiamo il default (es: TD00)
                    // ->default(function () {
                    //     $td00 = \App\Models\DocType::where('name', 'TD00')->first();

                    //     // Per i filtri multipli, il default DEVE essere un array semplice di ID (stringhe)
                    //     return $td00 ? [(string) $td00->id] : [];
                    // }),
                    // 3. Modifichiamo la query per ESCLUDERE i selezionati
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['values'],
                            fn (Builder $query, $values): Builder => $query->whereNotIn('doc_type_id', $values)
                        );
                    }),
                Filter::make('number')
                    ->form([
                        TextInput::make('number')
                            ->label('Numero Fattura'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['number'])) {
                            return $query->where('number', $data['number']);
                        }
                        return $query;
                    }),
                SelectFilter::make('paid')
                    ->label('Stato pagamento')
                    ->placeholder('Tutti gli stati')
                    ->options([
                        'si' => 'Pagate',
                        'no' => 'Non pagate',
                    ])
                    // ->query(function (Builder $query, array $data): Builder {
                    //     if (!isset($data['value'])) {
                    //         return $query;
                    //     }
                    //     $sql = 'total - (total_payment + total_notes)';
                    //     return $query->when($data['value'] === 'si', fn ($q) => $q->whereRaw("$sql <= 0"))
                    //                 ->when($data['value'] === 'no', fn ($q) => $q->whereRaw("$sql > 0"));
                    // })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        return $query->where('parent_id', null) // Escludi quelle con parent_id se necessario
                            ->when($data['value'] === 'si', function ($q) {
                                return $q->whereRaw("
                                    CASE
                                        WHEN (SELECT type FROM clients WHERE clients.id = invoices.client_id) = 'public'
                                        THEN no_vat_total - (total_payment + total_notes) <= 0
                                        ELSE total - (total_payment + total_notes) <= 0
                                    END
                                ");
                            })->when($data['value'] === 'no', function ($q) {
                                return $q->whereRaw("
                                    CASE
                                        WHEN (SELECT type FROM clients WHERE clients.id = invoices.client_id) = 'public'
                                        THEN no_vat_total - (total_payment + total_notes) > 0
                                        ELSE total - (total_payment + total_notes) > 0
                                    END
                                ");
                            });
                    })
                    ->preload(),
                SelectFilter::make('client_type')
                    ->label('Tipo cliente')
                    ->options(ClientType::class)
                    ->attribute(null)
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->whereHas('client', function ($q) use ($value) {
                                $q->where('type', $value);
                            });
                        }
                        return $query;
                    })
                    ->searchable()
                    ->preload(),
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
                    ->getOptionLabelFromRecordUsing(
                        // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                        fn (Model $record) => $record->denomination
                    )
                    ->searchable()
                    // ->preload()
                    ->columnSpan(2)
                    ->optionsLimit(5),
                SelectFilter::make('tax_type')->label('Entrata')
                    ->options(TaxType::class)
                    ->placeholder('Tutte')
                    ->searchable()
                    ->multiple()
                    ->preload(),
                SelectFilter::make('contract_id')->label('Contratto')
                    ->relationship('contract','office_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                    )
                    ->searchable()->preload()
                    ->columnSpan(2)
                    ->optionsLimit(5),
                SelectFilter::make('sdi_status')->label('Stato')->options(SdiStatus::class)
                    ->multiple()->searchable()->preload(),
                SelectFilter::make('accrual_type_id')
                    ->label('Gestione')
                    ->placeholder('Tutte')
                    ->options(function () {
                        return AccrualType::pluck('name', 'id')->toArray();
                    })
                    ->multiple()
                    ->preload(),
                SelectFilter::make('manage_type_id')
                    ->label('Servizio')
                    ->options(function () {
                        return ManageType::pluck('name', 'id')->toArray();
                    })
                    ->multiple()
                    ->columnSpan(2)
                    ->preload(),
                SelectFilter::make('invoice_year_from')
                    ->label('Anno fattura da')
                    ->attribute(null)
                    ->selectablePlaceholder(false)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();

                        // 1. Recuperiamo l'anno meno recente
                        $minYear = \App\Models\Invoice::query()
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->min('year') ?? now()->year;

                        // 2. Recuperiamo la lista degli anni per il menu
                        $years = \App\Models\Invoice::query()
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('year')
                            ->distinct()
                            ->pluck('year', 'year')
                            ->toArray();

                        // 3. Uniamo "Tutti" (puntando al minYear) con l'elenco degli anni
                        // Usiamo l'operatore + per preservare le chiavi numeriche
                        return [
                            now()->year => 'Anno corrente',
                            $minYear => 'Tutti',
                        ] + $years;
                    })
                    // ->options(function () {
                    //     $tenant = \Filament\Facades\Filament::getTenant();
                    //     return \App\Models\Invoice::query()
                    //         ->select('year')
                    //         ->distinct()
                    //         // ->where('flow', 'out')
                    //         ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                    //         ->orderByDesc('year')
                    //         ->pluck('year', 'year')
                    //         ->toArray();
                    // })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? now()->year;
                        if ($value) {
                            return $query->where('year', ">=", $value);
                        }
                        return $query;
                    }),
                SelectFilter::make('invoice_year_to')
                    ->label('Anno fattura a')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
                            ->select('year')
                            ->distinct()
                            // ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('year')
                            ->pluck('year', 'year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->where('year', "<=", $value);
                        }
                        return $query;
                    }),

                SelectFilter::make('invoice_budget_year_from')
                    ->label('Anno bilancio da')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
                            ->select('budget_year')
                            ->distinct()
                            // ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('budget_year')
                            ->pluck('budget_year', 'budget_year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->where('budget_year', ">=", $value);
                        }
                        return $query;
                    }),
                SelectFilter::make('invoice_budget_year_to')
                    ->label('Anno bilancio a')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
                            ->select('budget_year')
                            ->distinct()
                            // ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('budget_year')
                            ->pluck('budget_year', 'budget_year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->where('budget_year', "<=", $value);
                        }
                        return $query;
                    }),
                SelectFilter::make('invoice_accrual_year_from')
                    ->label('Anno competenza da')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
                            ->select('accrual_year')
                            ->distinct()
                            // ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('accrual_year')
                            ->pluck('accrual_year', 'accrual_year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->where('accrual_year', ">=", $value);
                        }
                        return $query;
                    }),
                SelectFilter::make('invoice_accrual_year_to')
                    ->label('Anno competenza da')
                    ->attribute(null)
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Invoice::query()
                            ->select('accrual_year')
                            ->distinct()
                            // ->where('flow', 'out')
                            ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                            ->orderByDesc('accrual_year')
                            ->pluck('accrual_year', 'accrual_year')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value) {
                            return $query->where('accrual_year', "<=", $value);
                        }
                        return $query;
                    }),
                Filter::make('total_range')
                    ->columns(2)
                    ->form([
                        TextInput::make('total_from')
                            ->label('Totale da')
                            ->columnSpan(1),
                        TextInput::make('total_to')
                            ->label('Totale a')
                            ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['total_from'])) {
                            $query->where('total', '>=', $data['total_from']);
                        }
                        if (! empty($data['total_to'])) {
                            $query->where('total', '<=', $data['total_to']);
                        }
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['total_from'] && $data['total_to']) {
                            return "Importo da " . number_format($data['total_from'], 2, ',', '.') . " fino a " . number_format($data['total_to'], 2, ',', '.');
                        }
                        if ($data['total_from']) {
                            return "Importo da " . number_format($data['total_from'], 2, ',', '.');
                        }
                        if ($data['total_to']) {
                            return "Importo fino a " . number_format($data['total_to'], 2, ',', '.');
                        }
                        return null;
                    }),
            // ],layout: FiltersLayout::Modal)->filtersFormColumns(4)
            ])
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF (formato AssoSoftware)')
                    ->icon('phosphor-file-pdf-duotone')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->pdf_path ? Storage::temporaryUrl($record->pdf_path, now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        // Aggiungi il controllo che pdf_path non sia null/vuoto
                        return $record &&
                            !empty($record->pdf_path) &&
                            Storage::disk(config('filesystems.default'))->exists($record->pdf_path);
                    }),

                Tables\Actions\Action::make('download_xml')
                    ->label('')
                    ->tooltip('Scarica XML')
                    ->icon('tabler-file-type-xml')
                    ->iconSize('lg')
                    ->url(fn($record): ?string => $record->xml_path ? Storage::temporaryUrl($record->xml_path, now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(function($record) {
                        // Aggiungi il controllo che xml_path non sia null/vuoto
                        return $record &&
                            !empty($record->xml_path) &&
                            Storage::disk(config('filesystems.default'))->exists($record->xml_path);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('Export')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->openUrlInNewTab()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            echo Pdf::loadHTML(
                                Blade::render('prints/invoices_list', ['records' => $records])
                            )->stream();
                        }, 'lista_fatture_'.date('dFY').'.pdf');
                    }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InvoiceItemsRelationManager::class,
            SdiNotificationsRelationManager::class,
            CreditNotesRelationManager::class,
            ActivePaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewInvoices::route('/'),
            'create' => Pages\CreateNewInvoice::route('/create'),
            'edit' => Pages\EditNewInvoice::route('/{record}/edit'),
            'view' => Pages\ViewNewInvoice::route('/{record}'),
        ];
    }

    // public static function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['flow'] = 'out';
    //     return $data;
    // }

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

        if ($client && $client->type) {
            $sectional = \App\Models\Sectional::where('company_id', Filament::getTenant()->id)
                ->where('client_type', $client->type->value)
                ->first();
            if ($sectional) {
                $set('sectional_id', $sectional->id);
                $number = NewInvoiceResource::calculateNextInvoiceNumber($get);
                $set('number', $number);
                NewInvoiceResource::invoiceNumber($get, $set);
            } else {
                $set('sectional_id', null);
                $set('number', null);
                NewInvoiceResource::invoiceNumber($get, $set);
                Notification::make()
                    ->title('Nessun sezionario trovato per il tipo di cliente selezionato.')
                    ->warning()
                    ->send();
            }
        }


        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }

    public static function saveContract(array $data, NewContract $contract): void
    {
        $contract->company_id = Filament::getTenant()->id;
        $contract->client_id = $data['client_id'];
        $contract->tax_type = $data['tax_type'];
        $contract->start_validity_date = $data['start_validity_date'];
        $contract->end_validity_date = $data['end_validity_date'];
        // $contract->accrual_type_id = $data['accrual_type_id'];
        $contract->accrual_types = $data['accrual_types'];
        $contract->payment_type = $data['payment_type'];
        $contract->cig_code = $data['cig_code'];
        $contract->cup_code = $data['cup_code'];
        $contract->office_code = $data['office_code'];
        $contract->office_name = $data['office_name'];
        $contract->amount = $data['amount'];
        $contract->save();
        Notification::make()
            ->title('Contratto salvato con successo')
            ->success()
            ->send();
    }

    public static function invoiceNumber(Get $get, Set $set){

        if($get('art_73')) {
            $number = "";
            $date = $get('invoice_date');
            for($i=strlen($get('number'));$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$get('number');
            $set('invoice_uid', $number."/".$date);
        }
        else if(empty($get('number')) || empty($get('sectional_id')) || empty($get('year')))
            $set('invoice_uid', null);
        else{
            $number = "";
            $sectional = Sectional::find($get('sectional_id'))->description;
            for($i=strlen($get('number'));$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$get('number');
            $set('invoice_uid', $number."/".$sectional."/".$get('year'));
        }

    }

    public static function calculateNextInvoiceNumber(Get $get): ?int
    {
        $year = $get('year');
        $sectionalId = $get('sectional_id');
        $art73 = $get('art_73');
        $invoiceDate = $get('invoice_date');

        if ($art73) {
            $maxNumber = \App\Models\Invoice::where('invoice_date', $invoiceDate)
                ->where('art_73', true)
                ->where('company_id', Filament::getTenant()->id)
                ->max('number');

            if ($maxNumber !== null) {
                return $maxNumber + 1;
            }

            return 1;
        }
        else if ($year && $sectionalId) {
            $maxNumber = \App\Models\Invoice::where('year', $year)
                ->where('sectional_id', $sectionalId)
                ->where('company_id', Filament::getTenant()->id)
                ->max('number');

            if ($maxNumber !== null) {
                return $maxNumber + 1;
            }

            $sectional = \App\Models\Sectional::find($sectionalId);
            return $sectional?->progressive;
        }

        return null;
    }

    protected static function updateDescription(Get $get, Set $set, $new): void
    {
        $description = '';
        $docTypeId = $get('doc_type_id');
        $year = substr($get('budget_year'), 2);

        if (filled($docTypeId)) {

            $docType = DocType::with('docGroup')->find($docTypeId);

            if ($docType?->docGroup?->name === 'Note di variazione') {
                if($new === 'new_doc'){
                    $set('description', '');
                }
                if($new === 'new_ref'){
                    $set('reference_date_from', '');
                    $set('reference_date_to', '');
                    $set('reference_number_from', '');
                    $set('reference_number_to', '');
                    $set('total_number', '');
                }

                $docType = DocType::find($get('doc_type_id'))->description;
                $description = '(ab' . $year .') ' . $docType;

                $reversalGroupType = ReversalGroupType::tryFrom($get('reversal_group_type'))?->getLabel();
                if($reversalGroupType)
                    $description .= ' a storno ' . lcfirst($reversalGroupType);

                $parent = Invoice::find($get('parent_id'));
                if($parent){
                    $description .= ' di ' . lcfirst($parent?->docType->description);
                    $description .= ' n.ro ' . $parent?->getNewInvoiceNumber();
                    $description .= ' del ' . \Carbon\Carbon::parse($parent?->invoice_date)->format('d/m/Y');

                    $motivation = ReversalMotivationType::find($get('reversal_motivation_type_id'))?->name;
                    if($motivation)
                        $description .= ' per ' . lcfirst($motivation) . '.';
                }
            } else {
                if($new === 'new_doc'){
                    $set('description', '');
                    $set('reference_date_from', '');
                    $set('reference_date_to', '');
                    $set('reference_number_from', '');
                    $set('reference_number_to', '');
                    $set('total_number', '');
                }
                if($new === 'new_ref'){
                    $set('reference_date_from', '');
                    $set('reference_date_to', '');
                    $set('reference_number_from', '');
                    $set('reference_number_to', '');
                    $set('total_number', '');
                }

                $contractDescription = NewContract::find($get('contract_id'))?->lastDetail?->invoice_description ?? '';

                $description = '(ab' . $year .') ' . $contractDescription . ' ';

                // $description .= 'Corrispettivo per ' . strtolower($accrualType) . ' ';

                $invoiceReference = $get('invoice_reference');
                if ($invoiceReference) {
                    $dateFrom = $get('reference_date_from');
                    $dateTo = $get('reference_date_to');
                    if ($dateFrom) {
                        $description .= 'per il periodo dal ' . static::formatDate($dateFrom);

                        if ($dateTo) {
                            $description .= ' al ' . static::formatDate($dateTo);
                        }
                    }

                    $numberFrom = $get('reference_number_from');
                    $numberTo = $get('reference_number_to');
                    if ($numberFrom) {
                        $description .= 'dal verbale numero ' . $numberFrom;

                        if ($numberTo) {
                            $description .= ' al verbale numero ' . $numberTo;

                            $total = $get('reference_number_to') - $get('reference_number_from') + 1;
                            $set('total_number', $total);
                            if ($total) {
                                $description .= ' per un totale di ' . $total . ' verbali';
                            }
                        }
                    }


                }
            }

            // $set('description', trim($description));
        }

        $set('description', trim($description));
    }

    protected static function formatDate($date): string
    {
        if (is_string($date)) {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        }

        if ($date instanceof \Carbon\Carbon || $date instanceof \DateTime) {
            return $date->format('d/m/Y');
        }

        return (string) $date;
    }
}
