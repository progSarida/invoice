<?php

namespace App\Filament\Exports;

use App\Models\Client;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ClientExporter extends Exporter
{
    protected static ?string $model = Client::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('#'),
            ExportColumn::make('company.name')
                ->label('Azienda')
                ->enabledByDefault(false),
            ExportColumn::make('type')
                ->label('Tipo')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('denomination')
                ->label('Denominazione'),
            ExportColumn::make('address')
                ->label('Indirizzo'),
            ExportColumn::make('zip_code')
                ->label('Cap'),
            ExportColumn::make('city.name')
                ->label("Citta'"),
            ExportColumn::make('city.province.name')
                ->label("Provincia"),
            ExportColumn::make('city.province.region.name')
                ->label("Regione"),
            ExportColumn::make('tax_code')
                ->label('Codice fiscale'),
            ExportColumn::make('vat_code')
                ->label('Partita IVA'),
            ExportColumn::make('email')
                ->label('Email'),
            ExportColumn::make('ipa_code')
                ->label('Codice univoco'),
            ExportColumn::make('created_at')
                ->enabledByDefault(false),
            ExportColumn::make('updated_at')
                ->enabledByDefault(false),
        ];
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your client export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
        $body = 'L\'esportazione dei clienti è stata completata e ' . number_format($export->successful_rows) . ' ' . $append1 . ' esportate.';

        if ($failedRowsCount) {
            $body .= '<br>' . number_format($failedRowsCount) . ' ' . $append2 . ' fallito l\'esportazione.';
        }

        return $body;
    }
}
