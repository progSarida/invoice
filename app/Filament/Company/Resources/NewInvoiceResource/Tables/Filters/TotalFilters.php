<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use App\Services\CurrencyService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TotalFilters
{
    public static function make(): array
    {
        return [
            // Riga 6
            Filter::make('total_range')
                ->columns(2)
                ->form([
                    TextInput::make('total_from')
                        ->label('Totale da')
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
                        ->label('Totale a')
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
                    $from = ! empty($data['total_from']) ? $data['total_from'] : null;
                    $to = ! empty($data['total_to']) ? $data['total_to'] : null;

                    if ($from === null && $to === null) {
                        return $query;
                    }

                    return $query->where(function (Builder $q) use ($from, $to) {
                        // Caso: totale della fattura comprensivo di IVA
                        $q->where(function (Builder $q2) use ($from, $to) {
                            $q2->where('is_total_with_vat', true)
                                ->when($from !== null, fn (Builder $q3) => $q3->where('total', '>=', $from))
                                ->when($to !== null, fn (Builder $q3) => $q3->where('total', '<=', $to));
                        })
                        // Caso: totale della fattura al netto dell'IVA
                        ->orWhere(function (Builder $q2) use ($from, $to) {
                            $q2->where('is_total_with_vat', false)
                                ->when($from !== null, fn (Builder $q3) => $q3->where('no_vat_total', '>=', $from))
                                ->when($to !== null, fn (Builder $q3) => $q3->where('no_vat_total', '<=', $to));
                        });
                    });
                })
                ->indicateUsing(function (array $data): ?string {
                    if ($data['total_from'] && $data['total_to']) {
                        return "Totale da " . $data['total_from'] . "€ fino a " . $data['total_to'] . '€';
                    }
                    if ($data['total_from']) {
                        return "Totale da " . $data['total_from'] . '€';
                    }
                    if ($data['total_to']) {
                        return "Totale fino a " . $data['total_to'] . '€';
                    }
                    return null;
                })
                ->columnSpan(8),
            Filter::make('ignore_limit')
                ->columns(18)
                ->form([
                    Toggle::make('filter_residue')
                        ->label("Ignora 'Dovuto' inferiore a")
                        ->live()
                        ->columnSpan(12),
                    TextInput::make('ignore_limit')
                        ->label('Importo')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $component) => $component->state(
                            number_format(CurrencyService::parseNumber($state) ?? 0, 2, ',', '.')
                        ))
                        ->formatStateUsing(fn ($state): ?string => $state === null ? null : number_format(CurrencyService::parseNumber($state) ?? 0, 2, ',', '.'))
                        ->extraInputAttributes(['class' => 'text-right'])
                        ->suffix('€')
                        ->columnSpan(6)
                        ->disabled(fn (Get $get) => $get('filter_residue') == false)
                        ->default(5),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if($data['filter_residue']){
                        $limit = CurrencyService::parseNumber($data['ignore_limit']);
                        if ($limit === null) {
                            return $query;
                        }
                        return $query->where(function (Builder $q) use ($limit) {
                            // Caso: totale della fattura al netto dell'IVA
                            $q->where(function ($q2) use ($limit) {
                                $q2->where('is_total_with_vat', false)
                                    ->whereNull('parent_id')
                                    ->whereRaw('(COALESCE(no_vat_total, 0) - (COALESCE(total_payment, 0) + COALESCE(total_notes, 0))) > ?', $limit);
                            })
                            // Caso: totale della fattura comprensivo di IVA
                            ->orWhere(function ($q3) use ($limit) {
                                $q3->where('is_total_with_vat', true)
                                    ->whereNull('parent_id')
                                    ->whereRaw('(COALESCE(total, 0) - (COALESCE(total_payment, 0) + COALESCE(total_notes, 0))) > ?', $limit);
                            });
                        });
                    }
                    else { return $query; }
                })
                ->indicateUsing(function (array $data): ?string {
                    if($data['filter_residue']){
                        return "Ignora documenti con residuo minore di " . number_format(CurrencyService::parseNumber($data['ignore_limit']) ?? 0, 2, ',', '.') . " €";
                    }
                    else {
                        return null;
                    }
                })
                ->columnSpan(8),
            // Tables\Filters\SelectFilter::make('balance')
            //     ->label('Quadratura saldi')
            //     ->options([
            //         '' => 'Tutti i documenti',
            //         'exclude' => 'Escludi quadrature saldi',
            //         'only' => 'Solo quadrature saldi',
            //     ])
            //     ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
            //         // Recuperiamo l'id del TD99 usando la cache
            //         $td99Id = \Illuminate\Support\Facades\Cache::remember('doc_type_td99_id', 3600, function () {
            //             return \App\Models\DocType::where('name', 'TD99')->first()?->id;
            //         });

            //         if (! $td99Id) return;

            //         // Applichiamo la query in base alla selezione dell'utente
            //         $query->when($data['value'] === 'exclude', fn ($q) => $q->where('doc_type_id', '!=', $td99Id))
            //             ->when($data['value'] === 'only', fn ($q) => $q->where('doc_type_id', $td99Id));
            //     })
            //     ->indicateUsing(function (array $data): ?string {
            //         if (! $data['value']) return null;

            //         return match ($data['value']) {
            //             'exclude' => 'Senza quadrature saldi',
            //             'only' => 'Solo quadrature saldi',
            //             default => null,
            //         };
            //     })
            //     ->columnSpan(2),
        ];
    }
}
