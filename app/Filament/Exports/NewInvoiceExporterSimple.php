<?php

namespace App\Filament\Exports;

use App\Enums\TaxType;
use App\Models\BankAccount;
use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;

class NewInvoiceExporterSimple extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('#')
                ->enabledByDefault(false),
            ExportColumn::make('doc_type_id')
                ->label('Tipo')
                ->formatStateUsing(fn ($record) => $record->docType?->description),
            ExportColumn::make('parent_id')
                ->label('Fattura stornata')
                ->enabledByDefault(false)
                ->formatStateUsing(function ($record) {
                    if(!$record->flow) $parent = $record->InvoiceNumber();
                    else $parent = $record->getNewInvoiceNumber();
                    return $parent ?? 'N\A';
                }),
            ExportColumn::make('invoice_number')
                ->label('Numero')
                ->formatStateUsing(function ($record) {
                    return $record->getNewInvoiceNumber() ?? 'N/A';
                }),
            ExportColumn::make('invoice_date')
                ->label('Data')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y') : null),
            ExportColumn::make('description')
                ->label('Descrizione'),
            ExportColumn::make('client.denomination')
                ->label('Cliente'),
            ExportColumn::make('accrual_year')
                ->label('Anno competenza'),
            ExportColumn::make('budget_year')
                ->label('Anno bilancio'),
            ExportColumn::make('tax_type')
                ->label('Entrata')
                ->formatStateUsing(fn ($state) => $state ? TaxType::from($state->value)->getLabel() : null),
            ExportColumn::make('no_vat_total')
                ->label('Imponibile')
                ->formatStateUsing(function ($state, $record) {
                    $output = (float) $record->invoiceItems()->whereNull('postal_expense_id')->where('vat_code_type', '!=', 'vc06a')->sum('amount');
                    if($record->parent_id) $output = $output * -1; 
                    else $output = $output * 1;
                    return (float) number_format($output, 2, '.', '');
                }),
            ExportColumn::make('vat')
                ->label('IVA')
                ->formatStateUsing(function ($state, $record) {
                    $output = (float) $state;
                    if($record->parent_id) $output = $output * -1; 
                    else $output = $output * 1;
                    return (float) number_format($output, 2, '.', '');
                }),
            ExportColumn::make('art_15')
                ->label('Rimb. Art. 15')
                ->formatStateUsing(function ($record) {
                    $amount = (float) $record->invoiceItems()->whereNotNull('postal_expense_id')->sum('amount');
                    return (float) number_format($amount, 2, '.', '');
                }),
            ExportColumn::make('stamp')
                ->label('Imposta di bollo')
                ->formatStateUsing(function ($record) {
                    $stamp = $record->invoiceItems()->where('vat_code_type', 'vc06a')->exists();
                    if ($stamp) { return 2.00; }
                    else { return 0.00; }
                }),
            ExportColumn::make('total')
                ->label('Totale')
                ->formatStateUsing(function ($state, $record) {
                    $output = (float) $state;
                    if($record->parent_id) $output = $output * -1; 
                    else $output = $output * 1;
                    return (float) number_format($output, 2, '.', '');
                }),
            ExportColumn::make('total_notes')
                ->label('Totale note di credito'),
            ExportColumn::make('receive')
                ->label('Totale a doversi')
                ->formatStateUsing(function ($record) {
                    // VECCHIO CALCOLO
                    // $notRound = BankAccount::find($record?->bank_account_id)?->name != 'Giroconto';
                    // if($record->client?->type?->value == 'public' && $notRound)
                    //     $output = (float) $record->no_vat_total - (float) $record->total_notes;
                    // else
                    //     $output = (float) $record->total - (float) $record->total_notes;

                    // NUOVO CALCOLO USANDO is_total_with_vat
                    $newNoVatTotal = $record->no_vat_total - $record->creditNotes?->sum('no_vat_total');
                    $newVat = $record->vat - $record->creditNotes?->sum('vat');
                    $output = $record->is_total_with_vat ? $newNoVatTotal + $newVat : $newNoVatTotal;

                    if($record->docType?->name === 'TD04'){ $output = (float) 0.00;}
                    return (float) number_format($output, 2, '.', '');
                }),
            ExportColumn::make('total_payment')
                ->label('Totale pagamenti')
                ->formatStateUsing(function ($record, $state) {
                    $output = $state;
                    if($record->docType?->name === 'TD04'){ $output = (float) 0.00;}
                    return (float) number_format($output, 2, '.', '');
                }),
            ExportColumn::make('residue')
                ->label('Residuo')
                ->formatStateUsing(function ($record) {
                    // VECCHIO CALCOLO
                    // $notRound = BankAccount::find($record?->bank_account_id)?->name != 'Giroconto';
                    // if($record->client?->type?->value == 'public' && $notRound)
                    //     $output = (float) $record->no_vat_total - ($record->total_notes + $record->total_payment);
                    // else
                    //     $output = (float) $record->total - ($record->total_notes + $record->total_payment);

                    // NUOVO CALCOLO USANDO is_total_with_vat
                    $newNoVatTotal = $record->no_vat_total - $record->creditNotes?->sum('no_vat_total');
                    $newVat = $record->vat - $record->creditNotes?->sum('vat');
                    $temp = $newNoVatTotal- $record->total_payment;
                    $output = $record->is_total_with_vat ? $temp + $newVat : $temp;
                    
                    if($record->docType?->name === 'TD04'){ $output = (float) 0.00;}
                    return (float) number_format($output, 2, '.', '');
                }),
        ];
    }

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