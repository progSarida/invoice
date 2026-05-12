<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use App\Enums\NotifyType;
use App\Enums\ReinvoiceType;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\NewContract;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

class BaseInfoSection
{
    public static function make($latestDetailSubquery): Forms\Components\Section
    {
        return Forms\Components\Section::make('Informazioni di base per l\'identificazione della spesa postale')
            ->icon('heroicon-o-identification')
            ->collapsed(false)
            ->columns(12)
            ->schema([
                self::clientField(),
                self::notifyTypeField(),
                self::taxTypeField(),
                self::contractField($latestDetailSubquery),
                self::reinvoiceTypeField(),
            ]);
    }

    private static function clientField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('client_id')
            ->label('Cliente')
            ->getSearchResultsUsing(function (string $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $query = Client::query();
                $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                if (count($parts) >= 2) {
                    $query->where(function ($q) use ($parts) {
                        foreach ($parts as $part) {
                            $q->where('denomination', 'LIKE', "%{$part}%");
                        }
                    });
                } elseif (count($parts) === 1) {
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
                        $label = $record->denomination;
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
                return $record->denomination;
            })
            ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->denomination)
            ->required()
            ->searchable('denomination')
            ->live()
            ->placeholder('Seleziona')
            ->preload()
            ->optionsLimit(5)
            ->columnSpan(9);
    }

    private static function notifyTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('notify_type')
            ->label('Tipo notifica')
            ->required()
            ->options(NotifyType::class)
            ->searchable()
            ->live()
            ->placeholder('Seleziona')
            ->preload()
            ->columnSpan(3);
    }

    private static function taxTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('tax_type')
            ->label('Tipo entrata')
            ->required()
            ->options(function (Get $get) {
                $clientId = $get('client_id');
                if (empty($clientId)) {
                    return TaxType::class;
                }

                $contracts = NewContract::where('client_id', $clientId)->get();

                $labelToValue = [];
                foreach (TaxType::cases() as $case) {
                    $labelToValue[strtolower($case->getLabel())] = $case->value;
                }

                $taxTypesFromDb = [];
                foreach ($contracts as $contract) {
                    if (is_array($contract->tax_types)) {
                        $taxTypesFromDb = array_merge($taxTypesFromDb, $contract->tax_types);
                    }
                }

                $taxTypeValues = [];
                foreach ($taxTypesFromDb as $label) {
                    $normalizedLabel = strtolower($label);
                    if (isset($labelToValue[$normalizedLabel])) {
                        $taxTypeValues[] = $labelToValue[$normalizedLabel];
                    }
                }

                $taxTypeValues = array_unique(array_filter($taxTypeValues));

                if (empty($taxTypeValues)) {
                    return TaxType::class;
                }

                $options = [];
                foreach (TaxType::cases() as $case) {
                    if (in_array($case->value, $taxTypeValues)) {
                        $options[$case->value] = $case->getLabel();
                    }
                }

                return empty($options) ? TaxType::class : $options;
            })
            ->searchable()
            ->live()
            ->placeholder('Seleziona')
            ->preload()
            ->columnSpan(3);
    }

    private static function contractField($latestDetailSubquery): Forms\Components\Select
    {
        return Forms\Components\Select::make('new_contract_id')
            ->label('Contratto')
            ->relationship(
                name: 'contract',
                modifyQueryUsing: function (Builder $query, Get $get) use ($latestDetailSubquery) {
                    $query->where('client_id', $get('client_id'))
                        ->whereJsonContains('tax_types', $get('tax_type'));

                    $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                        $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                    });

                    $query->select('new_contracts.*')
                        ->selectRaw('
                            COALESCE(
                                YEAR(new_contracts.start_validity_date),
                                YEAR(latest_details.latest_detail_date)
                            ) AS calculated_year'
                        )
                        ->distinct();

                    $query->orderByRaw('calculated_year DESC');

                    return $query;
                }
            )
            ->getSearchResultsUsing(function (string $search, Get $get) use ($latestDetailSubquery) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $query = NewContract::query();

                $query->leftJoinSub($latestDetailSubquery, 'latest_details', function (JoinClause $join) {
                    $join->on('new_contracts.id', '=', 'latest_details.contract_id');
                });

                $query->where('client_id', $get('client_id'));

                $query->where(function ($q) use ($search) {
                    $q->where('new_contracts.cig_code', 'LIKE', "%{$search}%");

                    if (is_numeric($search)) {
                        $q->orWhereRaw('
                            YEAR(COALESCE(
                                new_contracts.start_validity_date,
                                latest_details.latest_detail_date
                            )) = ?', [$search]
                        );
                    }
                });

                $query->select('new_contracts.*')
                    ->selectRaw('
                        COALESCE(
                            YEAR(new_contracts.start_validity_date),
                            YEAR(latest_details.latest_detail_date)
                        ) AS calculated_year'
                    )
                    ->distinct();

                $query->orderByRaw('calculated_year DESC')
                    ->orderBy('new_contracts.id', 'DESC');

                return $query
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(function ($record) {
                        $label = "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}";
                        return [$record->id => $label];
                    })
                    ->toArray();
            })
            ->getOptionLabelFromRecordUsing(
                fn (Model $record) => "{$record->office_name} ({$record->office_code}) - TIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code} - {$record->calculated_year}"
            )
            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                $contract = NewContract::find($state);
                if ($contract) {
                    $set('reinvoice_type', $contract->reinvoice_type);
                } else {
                    $set('reinvoice_type', null);
                    $set('tax_type', null);
                }
            })
            ->required()
            ->searchable()
            ->live()
            ->preload()
            ->optionsLimit(5)
            ->columnSpan(6);
    }

    private static function reinvoiceTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('reinvoice_type')
            ->label('Tipo rifatturazione')
            ->options(ReinvoiceType::class)
            ->required()
            ->disabled()
            ->dehydrated()
            ->preload()
            ->columnSpan(3);
    }
}
