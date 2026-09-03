<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use App\Enums\ClientType;
use App\Enums\TaxType;
use App\Models\AccrualType;
use App\Models\Client;
use App\Models\ManageType;
use App\Models\NewContract;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecipientFilters
{
    public static function make(): array
    {
        return [
            // Riga 2
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
                ->columnSpan(['default' => 'full', 'lg' => 4])
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
                        ->limit(70)
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
                ->columnSpan(['default' => 'full', 'lg' => 12])
                ->optionsLimit(5),
            SelectFilter::make('contract_id')->label('Contratto')
                ->relationship('contract','office_name')
                ->getOptionLabelFromRecordUsing(
                    fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}"
                )
                ->getSearchResultsUsing(function (string $search) {
                    // Rimuovi spazi multipli e trim
                    $search = trim(preg_replace('/\s+/', ' ', $search));

                    // Query base con le stesse condizioni del relationship
                    $query = NewContract::query();

                    // Cerca separatori (spazio, virgola, slash, trattino)
                    $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                    // Un solo valore: cerca SOLO match esatto in number o year
                    $value = $parts[0];
                    $query->where(function ($q) use ($value) {
                        $q->where('office_code', 'LIKE', "%{$value}%")
                            ->orWhere('cig_code', 'LIKE', "%{$value}%")
                            ->orWhere('cup_code', 'LIKE', "%{$value}%");
                    });

                    return $query
                        ->limit(70)
                        ->get()
                        ->mapWithKeys(function ($record) {
                            $label = "{$record->office_name} ({$record->office_code})\nTIPO: {$record->payment_type->getLabel()} - CIG: {$record->cig_code}";

                            return [$record->id => $label];
                        })
                        ->toArray();
                })
                ->indicateUsing(function (array $data): ?string {
                    if (! $data['value']) { return null; }
                    $contract = NewContract::find($data['value']);
                    if (! $contract) { return null; }
                    $label = "{$contract->office_name} ({$contract->office_code}) CIG: {$contract->cig_code}";
                    return "Contratto: {$label}";
                })
                ->searchable()
                ->columnSpan(['default' => 'full', 'lg' => 8])
                ->preload()
                ->optionsLimit(5),

            // Riga 3
            SelectFilter::make('tax_type')->label('Entrata')
                ->options(TaxType::class)
                ->placeholder('Tutte')
                ->searchable()
                ->columnSpan(['default' => 'full', 'lg' => 4])
                ->multiple()
                ->preload(),
            SelectFilter::make('accrual_type_id')
                ->label('Gestione')
                ->placeholder('Tutte')
                ->options(function () {
                    return AccrualType::pluck('name', 'id')->toArray();
                })
                ->multiple()
                ->columnSpan(['default' => 'full', 'lg' => 10])
                ->preload(),
            SelectFilter::make('manage_type_id')
                ->label('Servizio')
                ->options(function () {
                    return ManageType::pluck('name', 'id')->toArray();
                })
                ->multiple()
                ->columnSpan(['default' => 'full', 'lg' => 10])
                ->preload(),
        ];
    }
}
