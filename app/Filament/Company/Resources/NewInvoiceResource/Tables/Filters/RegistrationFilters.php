<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class RegistrationFilters
{
    public static function make(): array
    {
        return [
            Filter::make('dateRegistration')
                ->columns(2)
                ->form([
                    DatePicker::make('date_from')
                        ->label('Data registrazione da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // $set('date_to', $state);
                            }
                        }),
                    DatePicker::make('date_to')
                        ->label('Data registrazione a')
                        ->extraInputAttributes(['class' => 'text-center']),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    // Modifichiamo la query per applicare i filtri in cascata senza interrompere l'esecuzione
                    return $query
                        ->when(
                            filled($data['date_from']),
                            fn (Builder $query) => $query->whereDate('created_at', '>=', $data['date_from'])
                        )
                        ->when(
                            filled($data['date_to']),
                            fn (Builder $query) => $query->whereDate('created_at', '<=', $data['date_to'])
                        );
                })
                ->columnSpan(12),
            SelectFilter::make('user_id')
                ->label('Registrate da')
                ->placeholder('Tutti gli utenti')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->columnSpan(6),
        ];
    }
}
