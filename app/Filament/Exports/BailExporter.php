<?php

namespace App\Filament\Exports;

use App\Models\Bail;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BailExporter extends Exporter
{
    protected static ?string $model = Bail::class;

    public static ?string $activeAtDate = null;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('client.denomination')
                ->label('Cliente'),
            ExportColumn::make('cig_code')
                ->label('CIG'),
            // ExportColumn::make('tax_types')
            //     ->label('Entrate')
            //     ->formatStateUsing(function ($state) {
            //         return !empty($state) ? implode(', ', $state) : 'N/A';
            //     }),
            ExportColumn::make('tax_types')
                ->formatStateUsing(function ($state) {
                    $array = is_string($state) ? json_decode($state, true) : $state;

                    if (is_array($array)) {
                        return implode(', ', $array);
                    }

                    return $state;
                }),
            ExportColumn::make('insurance.name')
                ->label('Compagnia'),
            ExportColumn::make('agency.name')
                ->label('Agenzia'),
            ExportColumn::make('bail_type')
                ->label('Tipo polizza')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('bill_number')
                ->label('Numero'),
            ExportColumn::make('bill_date')
                ->label('Data polizza'),
            ExportColumn::make('bill_attachment_path')
                ->label('Allegato polizza'),
            ExportColumn::make('condition_attachment_path')
                ->label('Allegato condizioni'),
            ExportColumn::make('premium')
                ->label('Premio')
                ->getStateUsing(function ($record) {
                    if (self::$activeAtDate) {
                        $detail = $record->selectedDetail(self::$activeAtDate);
                        return $detail?->premium;
                    }

                    return $record->lastDetail?->premium;
                }),
            // ExportColumn::make('start')
            //     ->label('Inizio')
            //     ->getStateUsing(fn ($record) => $record->lastDetail?->bill_start),
            ExportColumn::make('start')
                ->label('Inizio')
                ->getStateUsing(function ($record) {
                    if (self::$activeAtDate) {
                        $detail = $record->selectedDetail(self::$activeAtDate);
                        return $detail ? \Carbon\Carbon::parse($detail->bill_start)->format('d/m/Y') : NULL;
                    }
                    $detail = $record->lastDetail;
                    return $detail ? \Carbon\Carbon::parse($detail->bill_start)->format('d/m/Y') : NULL;
                }),
            // ExportColumn::make('deadline')
            //     ->label('Fine')
            //     ->getStateUsing(fn ($record) => $record->lastDetail?->bill_deadline),
            ExportColumn::make('deadline')
                ->label('Fine')
                ->getStateUsing(function ($record) {
                    if (self::$activeAtDate) {
                        $detail = $record->selectedDetail(self::$activeAtDate);
                        return $detail ? \Carbon\Carbon::parse($detail->bill_deadline)->format('d/m/Y') : NULL;
                    }
                    $detail = $record->lastDetail;
                    return $detail ? \Carbon\Carbon::parse($detail->bill_deadline)->format('d/m/Y') : NULL;
                }),
            ExportColumn::make('year_duration')
                ->label('Anni'),
            ExportColumn::make('month_duration')
                ->label('Mesi'),
            ExportColumn::make('day_duration')
                ->label('Giorni'),
            ExportColumn::make('note'),
            ExportColumn::make('created_at')->enabledByDefault(false),
            ExportColumn::make('updated_at')->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your bail export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
