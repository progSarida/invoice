<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use App\Enums\ShipmentDocType;
use App\Models\ShipmentType;
use App\Services\CurrencyService;
use Filament\Forms;
use Illuminate\Support\Carbon;

class PaymentSection
{
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Informazioni relative ai pagamenti delle spese')
            ->icon('heroicon-o-credit-card')
            ->collapsed(fn($record): bool => $record && $record->paymentInserted())
            ->visible(fn($record): bool => $record && self::show($record))
            ->schema([
                self::payedField(),
                self::paymentDateField(),
                self::paymentTotalField(),
                self::paymentInsertUserField(),
                self::paymentInsertDateField(),
            ])
            ->columns(3);
    }

    private static function payedField(): Forms\Components\Toggle
    {
        return Forms\Components\Toggle::make('payed')
            ->label('Spese pagate')
            ->formatStateUsing(function ($state, $record) {
                return $record?->passiveInvoice?->last_payment_date !== null && $record?->passiveInvoice?->passivePayments?->sum('amount') >= $record?->notify_expense_amount;
            })
            ->live();
    }

    private static function paymentDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('payment_date')
            ->label('Data pagamento')
            ->extraInputAttributes(['class' => 'text-center'])
            ->required()
            ->formatStateUsing(function ($state, $record) {
                if ($state) {
                    // Opzione A: Forza il fuso orario dell'app e poi prendi la data
                    // Questo sposta le 22:00 dell'8 maggio alle 00:00 del 9 maggio (ora italiana)
                    return Carbon::parse($state)->timezone(config('app.timezone'))->format('Y-m-d');
                }

                if ($record?->passive_invoice_id) {
                    $fallback = $record->passiveInvoice?->last_payment_date;
                    return $fallback ? Carbon::parse($fallback)->timezone(config('app.timezone'))->format('Y-m-d') : null;
                }

                return null;
            })
            ->helperText('In caso di più pagamenti, inserire la data dell\'ultimo pagamento');
    }

    private static function paymentTotalField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('payment_total')
            ->label('Totale pagamenti')
            ->live(onBlur: true)
            ->extraInputAttributes(['class' => 'text-right'])
            ->afterStateUpdated(function ($state, $component) {
                $float = CurrencyService::parseNumber($state);
                $formatted = number_format($float, 2, ',', '.');
                $component->state($formatted);
            })
            ->formatStateUsing(function ($state, $record) {
                $total = 0;
                // Se il campo ha già un valore nel database, tieni quello
                if ($state) $total = $state;

                // Se il campo è vuoto, calcola il valore suggerito
                if ($record && $record->passive_invoice_id) {
                    $total = $record->passiveInvoice?->passivePayments?->sum('amount');
                }

                return number_format($total, 2, ',', '.') ?? null;
            })
            ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
            ->inputMode('decimal')
            ->step(0.01)
            ->suffix('€');
    }

    private static function paymentInsertUserField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('payment_insert_user_id')
            ->label('Utente inserimento pagamento')
            ->disabled()
            ->visible(fn($record): bool => $record && $record->payment_insert_user_id)
            ->relationship('paymentInsertUser', 'name')
            ->searchable()
            ->preload()
            ->optionsLimit(5);
    }

    private static function paymentInsertDateField(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('payment_insert_date')
            ->label('Data inserimento pagamento')
            ->extraInputAttributes(['class' => 'text-center'])
            ->disabled()
            ->visible(fn($record): bool => $record && $record->payment_insert_date);
    }

    private static function show($record): bool
    {
        $shipmentType = ShipmentType::find($record->shipment_type_id);
        return $record &&
            (($record->expense_insert_user_id && $record->expense_insert_date) ||
            (!str_contains(strtolower($shipmentType?->name), ShipmentDocType::SPEDIZIONE->getShipmentType()) &&
             $record->shipment_insert_user_id && $record->shipment_insert_date));
    }
}
