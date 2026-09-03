<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DateFilters
{
    public static function make(): array
    {
        return [
            Filter::make('invoice_date_range')
                ->columnSpan(['default' => 'full', 'lg' => 12])
                ->columns(2)
                ->form([
                    DatePicker::make('invoice_from_date')
                        ->label('Data documento da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // $set('invoice_to_date', $state);
                            }
                        })
                        ->default(now()->year . '-01-01')
                        ->columnSpan(1),
                    DatePicker::make('invoice_to_date')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->default(now()->year . '-12-31')
                        ->label('Data documento a')
                        ->columnSpan(1),
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['invoice_from_date'])) {
                        $query->whereDate('invoice_date', '>=', $data['invoice_from_date']);
                    }
                    if (! empty($data['invoice_to_date'])) {
                        $query->whereDate('invoice_date', '<=', $data['invoice_to_date']);
                    }
                })
                ->indicateUsing(function (array $data): ?string {
                    if ($data['invoice_from_date'] && $data['invoice_to_date']) {
                        return "Data documento dal " . Carbon::parse($data['invoice_from_date'])->format('d/m/Y') . " al " . Carbon::parse($data['invoice_to_date'])->format('d/m/Y');
                    }
                    if ($data['invoice_from_date']) {
                        return "Data documento dal " . Carbon::parse($data['invoice_from_date'])->format('d/m/Y');
                    }
                    if ($data['invoice_to_date']) {
                        return "Data documento al " . Carbon::parse($data['invoice_to_date'])->format('d/m/Y');
                    }
                    return null;
                }),
            Filter::make('payment_date_range')
                ->columnSpan(['default' => 'full', 'lg' => 12])
                ->columns(2)
                ->form([
                    DatePicker::make('payment_from_date')
                        ->label('Data ultimo pagamento da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000)
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // $set('payment_to_date', $state);
                            }
                        })
                        ->columnSpan(1),
                    DatePicker::make('payment_to_date')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->label('Data ultimo pagamento a')
                        ->columnSpan(1),
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['payment_from_date'])) {
                        $query->whereDate('last_payment_date', '>=', $data['payment_from_date']);
                    }
                    if (! empty($data['payment_to_date'])) {
                        $query->whereDate('last_payment_date', '<=', $data['payment_to_date']);
                    }
                })
                ->indicateUsing(function (array $data): ?string {
                    if ($data['payment_from_date'] && $data['payment_to_date']) {
                        return "Data ultimo pagamento dal " . Carbon::parse($data['payment_from_date'])->format('d/m/Y') . " al " . Carbon::parse($data['payment_to_date'])->format('d/m/Y');
                    }
                    if ($data['payment_from_date']) {
                        return "Data ultimo pagamento dal " . Carbon::parse($data['payment_from_date'])->format('d/m/Y');
                    }
                    if ($data['payment_to_date']) {
                        return "Data ultimo pagamento al " . Carbon::parse($data['payment_to_date'])->format('d/m/Y');
                    }
                    return null;
                }),
        ];
    }
}
