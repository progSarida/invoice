<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use App\Models\PassiveInvoice;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class DocumentFilters
{
    public static function make(): array
    {
        return [
            SelectFilter::make('doc_type')
                ->label('Seleziona tipo documento')
                ->options(fn () => PassiveInvoice::docTypeOptions('desc'))               // tipi documento più presenti in cima
                ->multiple()
                ->searchable()
                ->columnSpan(18)
                ->preload(),
            SelectFilter::make('attached')
                ->label('Con allegati')
                ->options([
                    'yes' => 'Sì',
                    'no' => 'No',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (!isset($data['value'])) {
                        return $query;
                    }
                    return $query->when($data['value'] === 'yes', fn ($q) => $q->whereNotNull('attachments_path'))
                                ->when($data['value'] === 'no', fn ($q) => $q->whereNull('attachments_path'));
                })
                ->columnSpan(6)
                ->preload(),
            SelectFilter::make('exclude_doc_types')
                ->label('Escludi tipo documento')
                ->multiple()
                ->searchable()
                ->preload()
                ->columnSpanFull()
                ->options(fn () => PassiveInvoice::docTypeOptions('asc'))                // tipi documento meno presenti in cima
                ->getOptionLabelUsing(fn ($record) => $record?->description)
                ->default(function() {
                    // $excludedGroups = ['Note di variazione', 'Autofatture'];
                    $excludedGroups = ['Autofatture'];
                    $docTypes = PassiveInvoice::select('passive_invoices.doc_type')
                        ->join('doc_types', 'passive_invoices.doc_type', '=', 'doc_types.name')
                        ->join('doc_groups', 'doc_types.doc_group_id', '=', 'doc_groups.id')
                        ->whereIn('doc_groups.name', $excludedGroups)
                        ->distinct()
                        ->pluck('doc_type')
                        ->toArray();
                    return $docTypes;
                })
                ->query(function (Builder $query, array $data): Builder {
                    // dd($data);
                    return $query->when(
                        $data['values'],
                        fn (Builder $query, $values): Builder => $query->whereNotIn('doc_type', $values)
                    );
                }),
        ];
    }
}
