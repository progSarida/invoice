<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use Carbon\Carbon;
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
            Filter::make('create_date_range')
                ->columnSpan(12)
                ->columns(2)
                ->form([
                    DatePicker::make('create_from_date')
                        ->label('Data registrazione da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // $set('create_to_date', $state);
                            }
                        })
                        ->default(now()->year . '-01-01')
                        ->columnSpan(1),
                    DatePicker::make('create_to_date')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->default(now()->year . '-12-31')
                        ->label('Data registrazione a')
                        ->columnSpan(1),
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['create_from_date'])) {
                        $query->whereDate('created_at', '>=', $data['create_from_date']);
                    }
                    if (! empty($data['create_to_date'])) {
                        $query->whereDate('created_at', '<=', $data['create_to_date']);
                    }
                })
                ->indicateUsing(function (array $data): ?string {
                    if ($data['create_from_date'] && $data['create_to_date']) {
                        return "Data registrazione dal " . Carbon::parse($data['create_from_date'])->format('d/m/Y') . " al " . Carbon::parse($data['create_to_date'])->format('d/m/Y');
                    }
                    if ($data['create_from_date']) {
                        return "Data registrazione dal " . Carbon::parse($data['create_from_date'])->format('d/m/Y');
                    }
                    if ($data['create_to_date']) {
                        return "Data registrazione al " . Carbon::parse($data['create_to_date'])->format('d/m/Y');
                    }
                    return null;
                }),
            SelectFilter::make('user_id')
                ->label('Registrate da')
                ->placeholder('Tutti gli utenti')
                ->relationship('user', 'name')
                ->searchable()
                ->columnSpan(6)
                ->preload(),
        ];
    }
}
