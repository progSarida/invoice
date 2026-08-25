<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use App\Models\Supplier;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class SupplierFilters
{
    public static function make(): array
    {
        return [
            SelectFilter::make('supplier_id')
                ->label('Fornitore')
                // ->multiple()
                ->searchable()
                // ->preload()
                ->columnSpan(18)
                ->options(function () {
                    $suppliers = Supplier::select('suppliers.id', 'suppliers.denomination')
                        ->join('passive_invoices', 'suppliers.id', '=', 'passive_invoices.supplier_id')
                        ->distinct()
                        ->get()
                        ->pluck('denomination', 'id')
                        ->toArray();
                    return $suppliers;
                })
                ->getOptionLabelUsing(fn ($record) => $record?->description),
            SelectFilter::make('withholdings')
                ->label('Ritenuta d\'acconto')
                ->options([
                    'yes' => 'Con ritenuta',
                    'no' => 'Senza ritenuta',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (! isset($data['value'])) {
                        return $query;
                    }
                    return $query->when($data['value'] === 'yes', fn ($q) => $q->withholdings())
                                ->when($data['value'] === 'no', fn ($q) => $q->withoutWithholdings());
                })
                ->columnSpan(6),
        ];
    }
}
