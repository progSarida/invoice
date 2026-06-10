<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use App\Enums\NotifyType;
use App\Enums\ReinvoiceType;
use App\Enums\ShipmentDocType;
use App\Models\PassiveInvoice;
use App\Models\ShipmentType;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ExpenseSection
{
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Riferimenti alle spese della lavorazione/notifica richiesta')
            ->icon('heroicon-o-currency-euro')
            ->collapsed(fn($record): bool => $record && $record->expenseInserted())
            ->visible(function(Get $get, $record){
                return $record && $record->notificationInserted() &&
                        ($get('notify_type') === NotifyType::SPEDIZIONE->value && str_contains(strtolower($record->shipmentType?->name), ShipmentDocType::SPEDIZIONE->getShipmentType()));
            })
            ->schema([
                self::passiveInvoiceField(),
                self::notifyExpenseAmountField(),
                self::markExpenseAmountField(),
                // self::reinvoiceTypeField(),
                self::ibanField(),
                self::workPlaceholderField(),
                self::expenseInsertUserField(),
                self::expenseInsertDateField(),
            ])
            ->columns(3);
    }

    private static function passiveInvoiceField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('passive_invoice_id')
            ->label('Fattura passiva')
            ->required()
            ->options(function (Get $get): array {
                $supplierId = $get('supplier_id');
                if (!$supplierId) { return []; }

                return PassiveInvoice::where('supplier_id', $supplierId)
                    ->get()
                    ->mapWithKeys(function ($invoice) {
                        return [
                            $invoice->id => sprintf(
                                '%s del %s - %s',
                                $invoice->number,
                                $invoice->invoice_date->format('d/m/Y'),
                                $invoice->description
                            )
                        ];
                    })
                    ->toArray();
            })
            ->getSearchResultsUsing(function (Get $get, string $search): array {
                $supplierId = $get('supplier_id');
                if (!$supplierId) { return []; }

                $cleanedSearch = preg_replace('/[,\;\-\/]/', ' ', $search);

                $keywords = collect(explode(' ', $cleanedSearch))
                    ->map('trim')
                    ->filter()
                    ->all();

                if (empty($keywords)) { return []; }

                $query = PassiveInvoice::where('supplier_id', $supplierId);

                foreach ($keywords as $keyword) {
                    $query->where(function ($subQuery) use ($keyword) {
                        $subQuery->where('number', 'like', "%{$keyword}%")
                                ->orWhere('description', 'like', "%{$keyword}%")
                                ->orWhereDate('invoice_date', 'like', "%{$keyword}%");
                    });
                }

                return $query->get()
                    ->mapWithKeys(function ($invoice) {
                        return [
                            $invoice->id => sprintf(
                                '%s del %s - %s',
                                $invoice->number,
                                $invoice->invoice_date->format('d/m/Y'),
                                $invoice->description
                            )
                        ];
                    })
                    ->toArray();
            })
            ->getOptionLabelUsing(function ($value): ?string {
                $invoice = PassiveInvoice::find($value);
                if (!$invoice) { return null; }

                return sprintf(
                    '%s del %s - %s',
                    $invoice->number,
                    $invoice->invoice_date->format('d/m/Y'),
                    $invoice->description
                );
            })
            ->searchable()
            ->preload()
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, $state, $record) {
                if ($state) {
                    $passiveInvoice = PassiveInvoice::find($state);
                    $passiveInvoiceTotal = $passiveInvoice->total;
                    $set('notify_expense_amount', number_format($passiveInvoiceTotal, 2, ',', '.'));
                    $set('shipment_doc_number', $passiveInvoice->number);
                    $set('iban', $passiveInvoice->iban);
                    $set('shipment_doc_date', $passiveInvoice->invoice_date->toDateString());
                    $notifyAmount = str_replace(',', '.', str_replace('.', '', $get('notify_amount')));
                    // dd($notifyAmount, $passiveInvoiceTotal, 'STOP');
                    // dd($get('notify_amount') != $get('notify_expense_amount'), 'STOP');
                    if($notifyAmount != $passiveInvoiceTotal){
                        Log::warning("Il totale della fattura passiva selezionata è diverso dall'importo della notifica inserito");
                        Notification::make()
                            ->title("Attenzione!")
                            ->body("Il totale della fattura passiva selezionata è diverso dall'importo della notifica inserito")
                            ->warning()
                            ->send();
                    } else { Log::info("Il totale della fattura passiva selezionata corrisponde all'importo della notifica inserito"); }
                } else {
                    $set('notify_expense_amount', null);
                    $set('shipment_doc_number', null);
                    $set('iban', null);
                    $set('shipment_doc_date', null);
                    Log::info("Resetatti i valori della fattura passiva della spesa di notifica id: {$record->id}");
                }
            })
            ->columnSpanFull();
    }

    private static function notifyExpenseAmountField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('notify_expense_amount')
            ->label('Totale fattura')
            ->required()
            ->live(onBlur: true)
            ->extraInputAttributes(['class' => 'text-right'])
            ->afterStateUpdated(function ($state, $component) {
                $float = CurrencyService::parseNumber($state);
                $formatted = number_format($float, 2, ',', '.');
                $component->state($formatted);
            })
            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
            ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
            ->inputMode('decimal')
            ->step(0.01)
            ->suffix('€');
    }

    private static function markExpenseAmountField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('mark_expense_amount')
            ->label('Totale fattura')
            ->required()
            ->live(onBlur: true)
            ->extraInputAttributes(['class' => 'text-right'])
            ->afterStateUpdated(function ($state, $component) {
                $float = CurrencyService::parseNumber($state);
                $formatted = number_format($float, 2, ',', '.');
                $component->state($formatted);
            })
            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
            ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
            ->inputMode('decimal')
            ->step(0.01)
            ->visible(fn(Get $get): bool => $get('notify_type') === NotifyType::MESSO->value)
            ->suffix('€');
    }

    private static function reinvoiceTypeField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('reinvoice_type')
            ->label('Tipo rifatturazione')
            ->options(ReinvoiceType::class)
            ->required()
            ->disabled()
            ->dehydrated()
            ->preload();
    }

    private static function ibanField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('iban')
            ->label('IBAN')
            ->visible(function(Get $get){
                $shipmentType = ShipmentType::find($get('shipment_type_id'));
                return str_contains(strtolower($shipmentType?->name), ShipmentDocType::SPEDIZIONE->getShipmentType());
            })
            ->maxLength(255);
    }

    private static function workPlaceholderField(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('work')
            ->label('')
            ->visible(fn(Get $get): bool => $get('notify_type') !== NotifyType::MESSO->value);
    }

    private static function expenseInsertUserField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('expense_insert_user_id')
            ->label('Utente inserimento spese')
            ->disabled()
            ->visible(fn($record): bool => $record && $record->expense_insert_user_id)
            ->relationship('expenseInsertUser', 'name')
            ->searchable()
            ->preload()
            ->optionsLimit(5);
    }

    private static function expenseInsertDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('expense_insert_date')
            ->label('Data inserimento spese')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->visible(fn($record): bool => $record && $record->expense_insert_date);
    }
}
