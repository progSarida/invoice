<?php

namespace App\Filament\Exports;

use App\Models\ActivePayments;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ActivePaymentsExporter extends Exporter
{
    protected static ?string $model = ActivePayments::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('#')
                ->enabledByDefault(false),
            ExportColumn::make('company.name')
                ->label('Azienda'),
            ExportColumn::make('client')
                ->label('Cliente')
                ->formatStateUsing(fn ($record) => $record->invoice->client->denomination ?? 'N/D'),
            ExportColumn::make('invoice_id')
                ->label('Fattura')
                ->formatStateUsing(fn ($record) => $record->invoice->getNewInvoiceNumber() ?? 'N/D'),
            ExportColumn::make('amount')
                ->label('Importo'),
            ExportColumn::make('payment_date')
                ->label('Data pagamento')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('description')
                ->label('Descrizione'),
            ExportColumn::make('note')
                ->label('Note'),
            ExportColumn::make('registration_date')
                ->label('Data registrazione')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('registration_user_id')
                ->label('Registrato da')
                ->formatStateUsing(fn ($record) => $record->registrationUser->name ?? 'N/D'),
            ExportColumn::make('bank_account_id')
                ->label('Conto bancario')
                ->formatStateUsing(fn ($record) => $record->bankAccount->name ?? 'N/D'),
            ExportColumn::make('validated')
                ->formatStateUsing(fn ($state) => $state ? 'Sì' : 'No'),
            ExportColumn::make('validation_date')
                ->label('Data validazione')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : 'N/D'),
            ExportColumn::make('validation_user_id')
                ->label('Validato da')
                ->formatStateUsing(fn ($record) => $record->validationUser->name ?? 'N/D'),
            ExportColumn::make('created_at')
                ->enabledByDefault(false),
            ExportColumn::make('updated_at')
                ->enabledByDefault(false),
        ];
    }

    // public static function getCompletedNotificationBody(Export $export): string
    // {
    //     $body = 'Your active payments export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
        $body = 'L\'esportazione dei pagamenti è stata completata e ' . number_format($export->successful_rows) . ' ' . $append1 . ' esportate.';

        if ($failedRowsCount) {
            $body .= '<br>' . number_format($failedRowsCount) . ' ' . $append2 . ' fallito l\'esportazione.';
        }

        return $body;
    }
}
