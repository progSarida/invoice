<?php

namespace App\Filament\Exports;

use App\Models\PassivePayment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PassivePaymentExporter extends Exporter
{
    protected static ?string $model = PassivePayment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('#')
                ->enabledByDefault(false),
            ExportColumn::make('company.name')
                ->label('Azienda'),
            ExportColumn::make('supplier')
                ->label('Fornitore')
                ->formatStateUsing(fn ($record) => $record->passiveInvoice->supplier->denomination ?? 'N/D'),
            ExportColumn::make('passive_invoice_id')
                ->label('Fattura passiva')
                ->formatStateUsing(fn ($record) => $record->passiveInvoice ? $record->passiveInvoice->number . '/' . $record->passiveInvoice->invoice_date->format('d-m-Y') : 'N/D'),
            ExportColumn::make('amount'),
            ExportColumn::make('payment_date')
                ->label('Data pagamento')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('bank')
                ->label('Istituto finanziario'),
            ExportColumn::make('iban')
                ->label('IBAN'),
            ExportColumn::make('bank_account_id')
                ->label('Conto bancario')
                ->formatStateUsing(fn ($record) => $record->bankAccount->name ?? 'N/D'),
            ExportColumn::make('registration_date')
                ->label('Data registrazione')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('registration_user_id')
                ->label('Registrato da')
                ->formatStateUsing(fn ($record) => $record->registrationUser->name ?? 'N/D'),
            ExportColumn::make('validated'),
            ExportColumn::make('validation_date')
                ->label('Data validazione')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('validation_user_id')
                ->label('Registrato da')
                ->formatStateUsing(fn ($record) => $record->validationUser->name ?? 'N/D'),
            ExportColumn::make('created_at')
                ->enabledByDefault(false),
            ExportColumn::make('updated_at')
                ->enabledByDefault(false),
        ];
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your passive payment export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
        $body = 'L\'esportazione dei pagamenti passivi è stata completata e ' . number_format($export->successful_rows) . ' ' . $append1 . ' esportate.';

        if ($failedRowsCount) {
            $body .= '<br>' . number_format($failedRowsCount) . ' ' . $append2 . ' fallito l\'esportazione.';
        }

        return $body;
    }
}
