<?php

namespace App\Filament\Exports;

use App\Enums\PaymentType;
use App\Models\PassiveInvoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;

class PassiveInvoiceExporter extends Exporter
{
    protected static ?string $model = PassiveInvoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('#')
                ->enabledByDefault(false),
            ExportColumn::make('company.name')
                ->label('Azienda'),
            ExportColumn::make('supplier.denomination')
                ->label('Fornitore'),
            ExportColumn::make('parent_id')
                ->label('Documento di riferimento')
                ->formatStateUsing(fn ($record) => $record->parent->number ?? 'N/D'),
            ExportColumn::make('doc_type')
                ->label('Tipo documento')
                ->formatStateUsing(fn ($state, $record) => $record->docType?->description ?? '-'),
            ExportColumn::make('invoice_date')
                ->label('Data fattura')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('number')
                ->label('Numero'),
            ExportColumn::make('description')
                ->label('Descrizione'),
            ExportColumn::make('total')
                ->label('Totale'),
            ExportColumn::make('total_payment')
                ->label('Totale pagamento')
                ->formatStateUsing(fn ($state) => $state ? $state : 0.00),
            ExportColumn::make('sdi_code')
                ->label('Identificativo SDI'),
            ExportColumn::make('sdi_status')
                ->label('Stato SDI'),
            ExportColumn::make('payment_mode')
                ->label('Condizioni di pagamento')
                ->formatStateUsing(function ($state) {
                    if (! $state) { return null; }

                    $enum = collect(\App\Enums\PaymentMode::cases())
                        ->first(fn($case) => $case->getCode() === $state);

                    return $enum?->getLabel();
                }),
            ExportColumn::make('payment_type')
                ->label('Metodo di pagamento')
                ->formatStateUsing(function ($state) {
                    if (! $state) { return null; }

                    $enum = collect(PaymentType::cases())
                        ->first(fn($case) => $case->getCode() === $state);

                    return $enum?->getLabel();
                }),
            ExportColumn::make('payment_deadline')
                ->label('Scadenza')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('last_payment_date')
                ->label('Data ultimo pagamento')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('bank')
                ->label('Istituto finanziario'),
            ExportColumn::make('iban')
                ->label('IBAN'),
            ExportColumn::make('filename')
                ->enabledByDefault(false),
            ExportColumn::make('xml_path')
                ->enabledByDefault(false),
            ExportColumn::make('pdf_path')
                ->enabledByDefault(false),
            ExportColumn::make('created_at')
                ->enabledByDefault(false),
            ExportColumn::make('updated_at')
                ->enabledByDefault(false),
        ];
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your passive invoice export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
        $body = 'L\'esportazione delle fatture passive è stata completata e ' . number_format($export->successful_rows) . ' ' . $append1 . ' esportate.';

        if ($failedRowsCount) {
            $body .= '<br>' . number_format($failedRowsCount) . ' ' . $append2 . ' fallito l\'esportazione.';
        }

        return $body;
    }
}
