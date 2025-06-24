<?php

namespace App\Filament\Exports;

use App\Models\NewContract;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class NewContractExporter extends Exporter
{
    protected static ?string $model = NewContract::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('company.name')
                ->label('Azienda'),
            ExportColumn::make('client.denomination')
                ->label('Cliente'),
            ExportColumn::make('tax_type')
                ->label('Entrata')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('start_validity_date')
                ->label('Inizio contratto')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
            ExportColumn::make('end_validity_date')
                ->label('Fine contratto')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
            ExportColumn::make('accrualType.name')
                ->label('Competenza'),
            ExportColumn::make('payment_type')
                ->label('Tipo pagamento')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? null),
            ExportColumn::make('cig_code')
                ->label('CIG'),
            ExportColumn::make('cup_code')
                ->label('CUP'),
            ExportColumn::make('office_code')
                ->label('Codice ufficio'),
            ExportColumn::make('office_name')
                ->label('Nome ufficio'),
            ExportColumn::make('amount')
                ->label('Importo')
                ->formatStateUsing(fn ($state) => is_numeric($state) ? number_format($state, 2, ',', '.') : $state),
            ExportColumn::make('latest_contract_detail_number')
                ->label('Numero contratto')
                ->formatStateUsing(function ($record) {
                    $latestDetail = $record->contractDetails()->orderBy('created_at', 'desc')->first();
                    return $latestDetail?->number ?? 'N/A';
                }),
            ExportColumn::make('latest_contract_detail_type')
                ->label('Tipo contratto')
                ->formatStateUsing(function ($record) {
                    $latestDetail = $record->contractDetails()->orderBy('created_at', 'desc')->first();
                    return $latestDetail?->contract_type?->getLabel() ?? 'N/A';
                }),
            ExportColumn::make('latest_contract_detail_date')
                ->label('Data contratto')
                ->formatStateUsing(function ($record) {
                    $latestDetail = $record->contractDetails()->orderBy('created_at', 'desc')->first();
                    return $latestDetail?->date?->format('d/m/Y') ?? 'N/A';
                }),
            ExportColumn::make('latest_contract_detail_description')
                ->label('Descrizione')
                ->formatStateUsing(function ($record) {
                    $latestDetail = $record->contractDetails()->orderBy('created_at', 'desc')->first();
                    return $latestDetail?->description ?? 'N/A';
                }),
            ExportColumn::make('created_at')->enabledByDefault(false),
            ExportColumn::make('updated_at')->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your new contract export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
