<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class NumberAndDateFilters
{
    public static function make(): array
    {
        return [
            // Riga 4
            Filter::make('number')
                ->columns(2)
                ->form([
                    TextInput::make('number_from')
                        ->label('Numero Documento da')
                        ->extraInputAttributes(['class' => 'text-right'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $set('number_to', $state);
                            }
                        }),
                    TextInput::make('number_to')
                        ->extraInputAttributes(['class' => 'text-right'])
                        ->label('Numero Documento a'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    // Modifichiamo la query per applicare i filtri in cascata senza interrompere l'esecuzione
                    return $query
                        ->when(
                            filled($data['number_from']),
                            fn (Builder $query) => $query->where('number', '>=', $data['number_from'])
                        )
                        ->when(
                            filled($data['number_to']),
                            fn (Builder $query) => $query->where('number', '<=', $data['number_to'])
                        );
                })
                ->columnSpan(['default' => 'full', 'lg' => 12]),
            Filter::make('dateInvoice')
                ->columns(2)
                ->form([
                    DatePicker::make('date_from')
                        ->label('Data documento da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $set('date_to', $state);
                            }
                        }),
                    DatePicker::make('date_to')
                        ->label('Data documento a')
                        ->extraInputAttributes(['class' => 'text-center']),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    // Modifichiamo la query per applicare i filtri in cascata senza interrompere l'esecuzione
                    return $query
                        ->when(
                            filled($data['date_from']),
                            fn (Builder $query) => $query->whereDate('invoice_date', '>=', $data['date_from'])
                        )
                        ->when(
                            filled($data['date_to']),
                            fn (Builder $query) => $query->whereDate('invoice_date', '<=', $data['date_to'])
                        );
                })
                ->columnSpan(['default' => 'full', 'lg' => 12]),
        ];
    }
}
