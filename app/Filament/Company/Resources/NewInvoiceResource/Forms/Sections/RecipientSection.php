<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Enums\TaxType;
use App\Filament\Company\Resources\ClientResource;
use App\Filament\Company\Resources\ContractDetailResource;
use App\Filament\Company\Resources\NewContractResource;
use App\Filament\Company\Resources\NewInvoiceResource;
use App\Models\Client;
use App\Models\ContractDetail;
use App\Models\ManageType;
use App\Models\NewContract;
use App\Models\Sectional;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

class RecipientSection
{
    public static function make($latestDetailSubquery): Forms\Components\Section
    {
        return Section::make('Destinatario')
            ->collapsible()
            ->columns(6)
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
                            $client = Client::find($clientId);
                            if ($client && $client->type) {
                                $sectional = Sectional::where('company_id', Filament::getTenant()->id)
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
                        if(!$clientId || Client::find($clientId)?->isPublic())
                            $set('is_total_with_vat', false);
                        else
                            $set('is_total_with_vat', true);
                    })
                    ->searchable()
                    ->live()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(4)
                    ->hintAction(
                        Action::make('Nuovo')
                            ->icon('ri-user-2-line')
                            ->form(fn (Form $form) => ClientResource::modalForm($form))
                            ->modalHeading('')
                            ->modalWidth('7xl')
                            ->action(fn (array $data, Client $client, Get $get, Set $set) => NewInvoiceResource::saveClient($data, $client, $get, $set))
                    ),

                Forms\Components\Select::make('tax_type')->label('Entrata')
                    ->required(fn(Get $get): bool => filled($get('client_id')) && Client::find($get('client_id'))->isPublic() && !$get('level'))
                    ->columnSpan(2)
                    // ->options(TaxType::class)
                    ->options(function (Get $get) {
                        $clientId = $get('client_id');
                        if (empty($clientId)) {
                            return TaxType::class;
                        }

                        // Recupera i contratti del cliente
                        $contracts = NewContract::where('client_id', $clientId)
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
                        $query = NewContract::query();

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
                                // $label = "{$record->office_name} ({$record->office_code}) TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}";
                                $accruals = '';
                                if (!empty($record->accrual_types)) {
                                    $accrualList = is_array($record->accrual_types) 
                                        ? $record->accrual_types 
                                        : json_decode($record->accrual_types, true) ?? [];
                                    $accruals = implode(', ', $accrualList);
                                }

                                $manages = '';
                                $manageIds = $record->manage_types ?? [];
                                if (is_array($manageIds) && count($manageIds) > 0) {
                                    $manages = collect($manageIds)
                                        ->map(fn($id) => ManageType::find($id)?->name ?? "ID: {$id}")
                                        ->filter()
                                        ->implode(', ');
                                }

                                $label = $record->calculated_year . ' - ' . 'CIG: ' . $record->cig_code . ' - ' . 'TIPO: ' . $record->payment_type->getLabel() . ' - ' . 'GESTIONI: ' . $accruals . ' - ' . 'SERVIZI: ' . $manages;
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
                                return $record->calculated_year . ' - ' . 'CIG: ' . $record->cig_code . ' - ' . 'TIPO: ' . $record->payment_type->getLabel() . ' - ' . 'GESTIONI: ' . $accruals . ' - ' . 'SERVIZI: ' . $manages;
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
                                        ->map(fn($id) => ManageType::find($id)?->name ?? "ID: {$id}")
                                        ->filter()
                                        ->implode(', ');
                                }

                                // 4. Composizione finale
                                $paymentTypeLabel = $record->payment_type ? $record->payment_type->getLabel() : 'N/D';

                                return "{$year} - CIG: {$record->cig_code} - TIPO: {$paymentTypeLabel} - GESTIONI: " . ($accruals ?: 'Nessuna') . " - SERVIZI: " . ($manages ?: 'Nessuno');
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
                    ->required(fn(Get $get): bool => filled($get('client_id')) && Client::find($get('client_id'))->isPublic() && !$get('level'))
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
                            $contract = NewContract::find($state);
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
                        return ContractDetail::where('contract_id', $contractId)
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
                    ->columnSpan(2)
                    ->hintAction(
                        Action::make('Nuovo')
                            ->icon('tabler-contract')
                            ->visible(fn (Get $get) => filled($get('contract_id')))
                            ->fillForm(fn (Get $get): array => [
                                'contract_id' => $get('contract_id'),
                            ])
                            ->form(fn (Form $form) => ContractDetailResource::modalForm($form))
                            ->modalHeading('')
                            ->modalWidth('4xl')
                            ->action(fn (array $data, ContractDetail $detail, Get $get, Set $set) => NewInvoiceResource::saveDetail($data, $detail, $get('contract_id')))
                    ),
            ]);
    }
}
