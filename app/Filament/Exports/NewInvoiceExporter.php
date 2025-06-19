<?php

namespace App\Filament\Exports;

use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class NewInvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        $maxItems = Invoice::withCount('invoice_items')
                        ->where('flow', 'out')
                        ->orderBy('invoice_items_count', 'desc')
                        ->limit(1)
                        ->value('invoice_items_count') ?? 0;

        $invoiceItemColumns = [];

        for ($i = 0; $i < $maxItems; $i++) {
            $labelPrefix = 'Voce ' . ($i + 1);

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_description")
                ->label("{$labelPrefix} - Descrizione")
                ->formatStateUsing(function ($record) use ($i) {
                    $item = $record->invoice_items[$i] ?? null;
                    return $item?->description;
                });

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_amount")
                ->label("{$labelPrefix} - Importo")
                ->formatStateUsing(function ($record) use ($i) {
                    $item = $record->invoice_items[$i] ?? null;
                    return $item?->amount;
                });

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_vat_rate")
                ->label("{$labelPrefix} - Aliquota IVA")
                ->formatStateUsing(function ($record) use ($i) {
                    $item = $record->invoice_items[$i] ?? null;
                    $rate = $item?->vat_code_type?->getRate();

                    return $rate !== null ? $rate . '%' : null;
                });

            $invoiceItemColumns[] = ExportColumn::make("item_{$i}_total")
                ->label("{$labelPrefix} - Totale")
                ->formatStateUsing(function ($record) use ($i) {
                    $item = $record->invoice_items[$i] ?? null;
                    return $item?->total;
                });
        }

        return [
            ExportColumn::make('id')
                ->label('#'),
            ExportColumn::make('company.name')
                ->label('Azienda')
                ->enabledByDefault(false),
            ExportColumn::make('client.denomination')
                ->label('Cliente'),
            // ExportColumn::make('tender_id'),
            ExportColumn::make('parent_id')
                ->label('#')
                ->enabledByDefault(false),
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
                ->label('Competenza')
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
                ->label('Totale'),
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
                ->label('Totale pagamenti'),
            ExportColumn::make('last_payment_date')
                ->label('Data ultimo pagamento')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
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
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
