<?php

namespace App\Filament\Exports;

use App\Models\BankAccount;
use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Livewire\Component;

class NewInvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public function getCachedColumns(): array
    {
        if (isset($this->cachedColumns)) {
            return $this->cachedColumns;
        }

        $maxItems = collect($this->columnMap)
            ->keys()
            ->filter(fn($key) => str_starts_with($key, 'item_') && str_ends_with($key, '_description'))
            ->count();

        if ($maxItems === 0) {
            $maxItems = (int) ($this->options['max_items'] ?? 0);
        }

        return $this->cachedColumns = array_reduce(
            static::getColumns($maxItems),
            function (array $carry, ExportColumn $column): array {
                $carry[$column->getName()] = $column->exporter($this);
                return $carry;
            },
            []
        );
    }

    /**
     * Il metodo statico riceve il parametro calcolato a monte e genera la struttura
     */
    public static function getColumns(int $maxItems = 0): array
    {
        // $maxItems = Invoice::withCount('invoiceItems')
        //                 ->where('flow', 'out')
        //                 ->orderBy('invoice_items_count', 'desc')
        //                 ->limit(1)
        //                 ->value('invoice_items_count') ?? 0;

        $invoiceItemColumns = [];

        for ($i = 0; $i < $maxItems; $i++) {
            $labelPrefix = 'Voce ' . ($i + 1);

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_description")
                ->label("{$labelPrefix} - Descrizione")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->invoiceItems instanceof \Illuminate\Support\Collection
                        ? $record->invoiceItems->where('auto', false)
                        : $record->invoiceItems()->where('auto', false)->get();
                    $item = $items[$i] ?? null;
                    return $item?->description;
                });

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_amount")
                ->label("{$labelPrefix} - Importo")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->invoiceItems instanceof \Illuminate\Support\Collection
                        ? $record->invoiceItems->where('auto', false)
                        : $record->invoiceItems()->where('auto', false)->get();
                    $item = $items[$i] ?? null;
                    return $item && is_numeric($item->amount) ? number_format($item->amount, 2, ',', '') : ($item?->amount ?? '0,00');
                });

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_vat_rate")
                ->label("{$labelPrefix} - Aliquota IVA")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->invoiceItems instanceof \Illuminate\Support\Collection
                        ? $record->invoiceItems->where('auto', false)
                        : $record->invoiceItems()->where('auto', false)->get();
                    $item = $items[$i] ?? null;
                    $rate = $item?->vat_code_type?->getRate();

                    return $rate !== null ? $rate . '%' : null;
                });

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_total")
                ->label("{$labelPrefix} - Totale")
                ->formatStateUsing(function ($record) use ($i) {
                    $items = $record->invoiceItems instanceof \Illuminate\Support\Collection
                        ? $record->invoiceItems->where('auto', false)
                        : $record->invoiceItems()->where('auto', false)->get();
                    $item = $items[$i] ?? null;
                    return $item && is_numeric($item->total) ? number_format($item->total, 2, ',', '') : ($item?->total ?? '0,00');
                });
        }

        $output = [
            ExportColumn::make('id')
                ->label('#'),
            ExportColumn::make('company.name')
                ->label('Azienda')
                ->enabledByDefault(false),
            ExportColumn::make('client.denomination')
                ->label('Cliente'),
            // ExportColumn::make('tender_id'),
            ExportColumn::make('parent_id')
                ->label('Fattura stornata')
                ->enabledByDefault(false)
                ->formatStateUsing(function ($record) {
                    if(!$record->flow) $parent = $record->InvoiceNumber();
                    else $parent = $record->getNewInvoiceNumber();
                    return $parent ?? 'N\A';
                }),
            ExportColumn::make('check_validation')
                ->label('Validata')
                ->formatStateUsing(fn ($state) => $state == 'Y' ? 'SI' : 'NO'),
            ExportColumn::make('tax_type')
                ->label('Gestione')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('doc_type_id')
                ->label('Tipo')
                ->formatStateUsing(fn ($record) => $record->docType?->description),
            ExportColumn::make('number')
                ->label('Numero'),
            ExportColumn::make('sectional_id')
                ->label('Sezionale')
                ->formatStateUsing(fn ($record) => $record->sectional?->description),
            ExportColumn::make('year')
                ->label('Anno'),
            ExportColumn::make('invoice_date')
                ->label('Data')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
            ExportColumn::make('budget_year')
                ->label('Anno bilancio'),
            ExportColumn::make('accrual_type_id')
                ->label('Gestione')
                // ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
                ->formatStateUsing(fn ($state, $record) => $record->accrualType?->name ?? '-'),
            ExportColumn::make('accrual_year')
                ->label('Anno competenza'),
            ExportColumn::make('description')
                ->label('Descrizione'),

            ...$invoiceItemColumns,

            ExportColumn::make('free_description')
                ->label('Descrizione libera')
                ->enabledByDefault(false),
            // ExportColumn::make('vat_percentage')
            //     ->label('IVA %'),
            // ExportColumn::make('vat')
            //     ->label('IVA'),
            // ExportColumn::make('is_total_with_vat')
            //     ->label('Senza esenzione'),
            // ExportColumn::make('importo')
            //     ->label('Importo'),
            // ExportColumn::make('spese')
            //     ->label('Spese'),
            // ExportColumn::make('rimborsi')
            //     ->label('Rimborsi'),
            // ExportColumn::make('ordinario')
            //     ->label('Ordinario'),
            // ExportColumn::make('temporaneo')
            //     ->label('Temporaneo'),
            // ExportColumn::make('affissioni')
            //     ->label('Affissioni'),
            // ExportColumn::make('bollo')
            //     ->label('Bollo'),
            ExportColumn::make('total')
                ->label('Totale')
                // ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2, ',', '') : $state)
                ->formatStateUsing(function ($record) {
                    // Usa 'total' se il cliente è pubblico, altrimenti 'no_vat_total'
                    $value = 0;
                    $notRound = BankAccount::find($record?->bank_account_id)?->name != 'Giroconto';
                    if(!$record->parent_id){
                        $value = ($record->client?->type?->value == 'public' && $notRound)
                            ? $record->no_vat_total
                            : $record->total;
                    }
                    return is_numeric($value) ? number_format($value, 2, ',', '') : $value;
                }),
            ExportColumn::make('receive')
                ->label('Totale a doversi')
                ->formatStateUsing(function ($record) {
                    $notRound = BankAccount::find($record?->bank_account_id)?->name != 'Giroconto';
                    if($record->client?->type?->value == 'public' && $notRound)
                        $output = (float) $record->no_vat_total;
                    else
                        $output = (float) $record->total;
                    if($record->docType?->name === 'TD04'){ $output = (float) 0.00;}
                    return (float) number_format($output, 2, '.', '');
                }),
            // ExportColumn::make('no_vat_total')
            //     ->label('Totale senza IVA'),
            ExportColumn::make('bankAccount.name')
                ->label('Conto Bancario'),
            ExportColumn::make('payment_status')
                ->label('Stato pagamento')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('payment_type')
                ->label('Tipo pagamento')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('payment_days')
                ->label('Giorni'),
            ExportColumn::make('total_payment')
                ->label('Totale pagamenti')
                ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2, ',', '') : $state),
            ExportColumn::make('last_payment_date')
                ->label('Data ultimo pagamento')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
            ExportColumn::make('total_notes')
                ->label('Totale note di credito')
                ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2, ',', '') : $state),
            ExportColumn::make('residue')
                ->label('Residuo')
                ->formatStateUsing(function ($record) {
                    $notRound = BankAccount::find($record?->bank_account_id)?->name != 'Giroconto';
                    if($record->client?->type?->value == 'public' && $notRound)
                        $output = (float) $record->no_vat_total - ($record->total_notes + $record->total_payment);
                    else
                        $output = (float) $record->total - ($record->total_notes + $record->total_payment);
                    if($record->docType?->name === 'TD04'){ $output = (float) 0.00;}
                    return (float) number_format($output, 2, '.', '');
                }),
            ExportColumn::make('sdi_code')
                ->label('Codice SDI'),
            ExportColumn::make('sdi_status')
                ->label('Stato')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('sdi_date')
                ->label('Data')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
            ExportColumn::make('pdf_path')
                ->label('Pdf'),
            ExportColumn::make('xml_path')
                ->label('Xml'),
            ExportColumn::make('created_at')->enabledByDefault(false),
            ExportColumn::make('updated_at')->enabledByDefault(false),
        ];

        return $output;
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your invoice export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

    //     if ($failedRowsCount = $export->getFailedRowsCount()) {
    //         $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
    //     }

    //     return $body;
    // }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $append1 = $export->successful_rows === 1 ? 'riga è stata' : 'righe sono state';
        $failedRowsCount = $export->getFailedRowsCount();
        $append2 = $export->successful_rows === 1 ? 'riga ha ' : 'righe hanno';
        $body = 'L\'esportazione delle fatture è stata completata e ' . number_format($export->successful_rows) . ' ' . $append1 . ' esportate.';

        if ($failedRowsCount) {
            $body .= '<br>' . number_format($failedRowsCount) . ' ' . $append2 . ' fallito l\'esportazione.';
        }

        return $body;
    }
}
