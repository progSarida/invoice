<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use App\Services\CurrencyService;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TotalFilters
{
    public static function make(): array
    {
        return [
            Filter::make('total_range')
                ->columnSpan(['default' => 'full', 'lg' => 6])
                ->columns(2)
                ->form([
                    TextInput::make('total_from')
                        ->label('Dovuto da')
                        ->extraInputAttributes(['class' => 'text-right'])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $component) {
                            if($state === null) {
                                $component->state(null);
                                return;
                            }
                            $float = CurrencyService::parseNumber($state);
                            $formatted = number_format($float, 2, ',', '.');
                            $component->state($formatted);
                        })
                        ->formatStateUsing(function ($state) {
                            if (blank($state)) return null;

                            // Forza la conversione in float nel caso arrivi come stringa dal DB o dallo stato
                            $floatValue = (float) str_replace(',', '.', str_replace('.', '', $state));
                            // Oppure più semplicemente, se sei sicuro che il DB mandi un formato americano:
                            $floatValue = floatval($state);

                            return number_format($floatValue, 2, ',', '.');
                        })
                        ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                        ->columnSpan(1),
                    TextInput::make('total_to')
                        ->label('Dovuto a')
                        ->extraInputAttributes(['class' => 'text-right'])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $component) {
                            if($state === null) {
                                $component->state(null);
                                return;
                            }
                            $float = CurrencyService::parseNumber($state);
                            $formatted = number_format($float, 2, ',', '.');
                            $component->state($formatted);
                        })
                        // ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                        ->formatStateUsing(function ($state) {
                            if (blank($state)) return null;

                            // Forza la conversione in float nel caso arrivi come stringa dal DB o dallo stato
                            $floatValue = (float) str_replace(',', '.', str_replace('.', '', $state));
                            // Oppure più semplicemente, se sei sicuro che il DB mandi un formato americano:
                            $floatValue = floatval($state);

                            return number_format($floatValue, 2, ',', '.');
                        })
                        ->dehydrateStateUsing(fn ($state): ?float => CurrencyService::parseNumber($state))
                        ->columnSpan(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {

                        return $query
                            // Applica il filtro "Da" se presente
                            ->when(
                                $data['total_from'],
                                fn (Builder $query, $value): Builder => $query->where('total_doc', '>=', CurrencyService::parseNumber($value)),
                            )
                            // Applica il filtro "A" se presente
                            ->when(
                                $data['total_to'],
                                fn (Builder $query, $value): Builder => $query->where('total_doc', '<=', CurrencyService::parseNumber($value)),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['total_from'] && $data['total_to']) {
                            return "Dovuto da " . $data['total_from'] . "€ a " . $data['total_to'] . "€";
                        }
                        if ($data['total_from']) {
                            return "Dovuto da " . $data['total_from'] . "€";
                        }
                        if ($data['total_to']) {
                            return "Dovuto a " . $data['total_to'] . "€";
                        }
                        return null;
                    }),
        ];
    }
}
