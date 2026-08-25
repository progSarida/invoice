<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use Filament\Facades\Filament;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class DocumentFilters
{
    public static function make(): array
    {
        return [
            // Riga 1
            SelectFilter::make('doc_type_id')
                ->label('Seleziona tipo documento')
                // ->options(function () {
                //     return DocType::orderBy('doc_group_id')->pluck('description', 'id')->toArray();
                // })
                ->options(function (Get $record) {
                    $docs = Filament::getTenant()
                                ->docTypes()
                                ->select('doc_types.id', 'doc_types.description')
                                ->get();
                    return $docs ? $docs->pluck('description', 'id')->toArray() : [];
                })
                ->multiple()
                ->searchable()
                ->columnSpan(12)
                ->preload(),
            Tables\Filters\SelectFilter::make('exclude_doc_types')
                ->label('Escludi tipo documento')
                ->multiple()
                ->searchable()
                ->preload()
                ->columnSpan(12)
                // 1. Carichiamo le opzioni dal Tenant
                ->options(function () {
                    $tenant = Filament::getTenant();
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
        ];
    }
}
