<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use App\Models\Invoice;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class YearFilters
{
    public static function make(): array
    {
        return [
            // Riga 5
            SelectFilter::make('invoice_year_from')
                ->label('Anno documento da')
                ->attribute(null)
                ->selectablePlaceholder(false)
                ->options(function () {
                    $tenant = Filament::getTenant();

                    // 1. Recuperiamo l'anno meno recente
                    $minYear = Invoice::query()
                        ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                        ->min('year') ?? now()->year;

                    // 2. Recuperiamo la lista degli anni per il menu
                    $years = Invoice::query()
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
                })
                ->columnSpan(['default' => 'full', 'lg' => 4]),
            SelectFilter::make('invoice_year_to')
                ->label('Anno documento a')
                ->attribute(null)
                ->options(function () {
                    $tenant = Filament::getTenant();
                    return Invoice::query()
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
                })
                ->columnSpan(['default' => 'full', 'lg' => 4]),

            SelectFilter::make('invoice_budget_year_from')
                ->label('Anno bilancio da')
                ->attribute(null)
                ->options(function () {
                    $tenant = Filament::getTenant();
                    return Invoice::query()
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
                })
                ->columnSpan(['default' => 'full', 'lg' => 4]),
            SelectFilter::make('invoice_budget_year_to')
                ->label('Anno bilancio a')
                ->attribute(null)
                ->options(function () {
                    $tenant = Filament::getTenant();
                    return Invoice::query()
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
                })
                ->columnSpan(['default' => 'full', 'lg' => 4]),
            SelectFilter::make('invoice_accrual_year_from')
                ->label('Anno competenza da')
                ->attribute(null)
                ->options(function () {
                    $tenant = Filament::getTenant();
                    return Invoice::query()
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
                })
                ->columnSpan(['default' => 'full', 'lg' => 4]),
            SelectFilter::make('invoice_accrual_year_to')
                ->label('Anno competenza da')
                ->attribute(null)
                ->options(function () {
                    $tenant = Filament::getTenant();
                    return Invoice::query()
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
                })
                ->columnSpan(['default' => 'full', 'lg' => 4]),
        ];
    }
}
