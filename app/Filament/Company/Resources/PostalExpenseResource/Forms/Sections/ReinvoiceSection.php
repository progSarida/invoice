<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use App\Enums\ClientType;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ReinvoiceSection
{
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Estremi della rifatturazione delle spese della lavorazione/notifica')
            ->icon('heroicon-o-receipt-refund')
            ->collapsed(fn($record): bool => $record && $record->reinvoiceInserted())
            ->visible(fn($record): bool => $record && $record->reinvoice_type?->showReinvoice() && ($record->payment_insert_user_id && $record->payment_insert_date))
            ->schema([
                self::reinvoiceIdField(),
                self::reinvoiceNumberField(),
                self::reinvoiceDateField(),
                self::reinvoiceAmountField(),
                self::reinvoiceInsertUserField(),
                self::reinvoiceInsertDateField(),
            ])
            ->columns(3);
    }

    private static function reinvoiceIdField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('reinvoice_id')
            ->label('Fattura da emettere per rifatturazione')
            ->required()
            ->getSearchResultsUsing(function (string $search, Get $get) {
                $search = trim(preg_replace('/\s+/', ' ', $search));

                $query = Invoice::query()
                    ->where('contract_id', $get('new_contract_id'))
                    ->where('sdi_status', 'da_inviare')
                    ->whereNull('parent_id');

                $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                if (count($parts) >= 2) {
                    $value1 = is_numeric($parts[0]) ? (int) $parts[0] : null;
                    $value2 = is_numeric($parts[1]) ? (int) $parts[1] : null;

                    if ($value1 !== null && $value2 !== null) {
                        $query->where(function ($q) use ($value1, $value2) {
                            $q->where(function ($subQ) use ($value1, $value2) {
                                $subQ->where('number', $value1)
                                    ->where('year', $value2);
                            })
                            ->orWhere(function ($subQ) use ($value1, $value2) {
                                $subQ->where('year', $value1)
                                    ->where('number', $value2);
                            });
                        });
                    }
                } elseif (count($parts) === 1) {
                    if (is_numeric($parts[0])) {
                        $value = (int) $parts[0];
                        $query->where(function ($q) use ($value) {
                            $q->where('number', $value)
                            ->orWhere('year', $value)
                            ->orWhere('description', $value);
                        });
                    }
                }

                return $query
                    ->with(['client', 'sectional'])
                    ->orderBy('invoice_date', 'desc')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(function ($record) {
                        $descrizione = $record->description ?? '';
                        $sectional = $record->sectional?->description ?? 'N/A';
                        $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                        $year = $record->year ?? '????';
                        $label = "{$number}/{$sectional}/{$year} - {$descrizione}";

                        return [$record->id => $label];
                    })
                    ->toArray();
            })
            ->getOptionLabelUsing(function ($value): ?string {
                $record = Invoice::find($value);

                if (!$record) {
                    return null;
                }

                $descrizione = $record->description ?? '';
                $sectional = $record->sectional?->description ?? 'N/A';
                $number = str_pad($record->number ?? 0, 3, '0', STR_PAD_LEFT);
                $year = $record->year ?? '????';

                return "{$number}/{$sectional}/{$year} - {$descrizione}";
            })
            ->searchable()
            ->preload()
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                if($state){
                    $invoice = Invoice::find($state);
                    if ($invoice) {
                        $set('reinvoice_number', $invoice->number);
                        $set('reinvoice_date', $invoice->invoice_date->format('Y-m-d'));
                        if($invoice->client->type == ClientType::PUBLIC)
                            $invoiceTot = $invoice->no_vat_total;
                        elseif($invoice->client->type == ClientType::PUBLIC)
                            $invoiceTot = $invoice->total;
                        $notifyAmount = (float) str_replace(['.', ','], ['', '.'], $get('notify_amount'));
                        // $expenseAmount = (float) str_replace(['.', ','], ['', '.'], $get('notify_expense_amount'));
                        $markAmount = (float) str_replace(['.', ','], ['', '.'], $get('mark_expense_amount'));
                        $newTotal = $invoiceTot + $notifyAmount + $markAmount;
                        $clean = preg_replace('/[^\d,\.-]/', '', $newTotal);
                        $number = str_replace(',', '.', $clean);
                        $float = floatval($number);
                        $formatted = number_format($float, 2, ',', '.');
                        $set('reinvoice_amount', $formatted);
                    }
                }
            })
            ->columnSpanFull();
    }

    private static function reinvoiceNumberField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('reinvoice_number')
            ->label('Numero fattura da emettere')
            ->required()
            ->extraInputAttributes(['class' => 'text-right'])
            ->disabled()
            ->dehydrated()
            ->maxLength(255);
    }

    private static function reinvoiceDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('reinvoice_date')
            ->label('Data fattura da emettere')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->dehydrated()
            ->required();
    }

    private static function reinvoiceAmountField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('reinvoice_amount')
            ->label('Importo fattura da emettere')
            ->required()
            ->live(onBlur: true)
            ->extraInputAttributes(['class' => 'text-right'])
            ->disabled()
            ->dehydrated()
            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
            ->dehydrateStateUsing(fn ($state): ?float => is_string($state) ? (float) str_replace(',', '.', str_replace('.', '', $state)) : $state)
            ->inputMode('decimal')
            ->step(0.01)
            ->suffix('€');
    }

    private static function reinvoiceInsertUserField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('reinvoice_insert_user_id')
            ->label('Utente inserimento rifatturazione')
            ->disabled()
            ->visible(fn($record): bool => $record && $record->reinvoice_insert_user_id)
            ->relationship('reinvoiceInsertUser', 'name')
            ->searchable()
            ->preload()
            ->optionsLimit(5);
    }

    private static function reinvoiceInsertDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('reinvoice_insert_date')
            ->label('Data inserimento rifatturazione')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->visible(fn($record): bool => $record && $record->reinvoice_insert_date);
    }
}
