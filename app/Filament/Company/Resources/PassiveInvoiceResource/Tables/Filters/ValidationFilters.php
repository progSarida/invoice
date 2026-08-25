<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use App\Enums\PiValidationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ValidationFilters
{
    public static function make(): array
    {
        return [
            SelectFilter::make('pi_validation_status')
                ->label('Validazione')
                // hintIcon() è un metodo dei Field: va applicato al Select che il filtro costruisce, non al filtro stesso
                ->modifyFormFieldUsing(fn (Select $field): Select => $field->hintIcon(
                    'heroicon-o-information-circle',
                    tooltip: '\'Tutti i validati\' mostra le fatture che hanno avuto un qualsiasi tipo di validazione; le voci successive a \'Da validare\' sono i singoli casi di validazione'
                ))
                // helperText() è un metodo dei Field: va applicato al Select che il filtro costruisce, non al filtro stesso.
                // HtmlString evita l'escape di Blade, così il <br> va davvero a capo
                // ->modifyFormFieldUsing(fn (Select $field): Select => $field->helperText(new HtmlString(
                //     "<strong>Tutti i validati</strong> mostra le fatture che hanno avuto un qualsiasi tipo di validazione;<br>"
                //     . "le voci successive a <strong>Da validare</strong> sono i singoli casi di validazione"
                // )))
                ->columnSpan(6)
                // ->options(PiValidationStatus::class)
                ->options(fn () => [
                        'validati' => 'Tutti i validati',   // La tua opzione custom
                    ] + PiValidationStatus::class::toArray()
                )
                ->query(function (Builder $query, array $data) {
                    $value = $data['value'] ?? null;

                    switch($value){
                        case PiValidationStatus::NO_STATUS->value:
                            return $query->whereNull('pi_validation_id');
                            break;
                        case PiValidationStatus::OK->value:
                        case PiValidationStatus::WAIT->value:
                        case PiValidationStatus::BLOCK->value:
                        case PiValidationStatus::VIEW->value:
                            return $query->whereHas('piValidation', function ($q) use ($value) {
                                    $q->where('pi_validation_status', $value);
                                });
                            break;
                        case 'validati':
                            return $query->whereNotNull('pi_validation_id');
                            break;
                        default:
                            return $query;
                            break;
                    }
                })
                ->searchable()
                ->preload(),
            Filter::make('dateValidation')
                ->columns(2)
                ->form([
                    DatePicker::make('date_from')
                        ->label('Data validazione da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // $set('date_to', $state);
                            }
                        }),
                    DatePicker::make('date_to')
                        ->label('Data validazione a')
                        ->extraInputAttributes(['class' => 'text-center']),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    // Modifichiamo la query per applicare i filtri in cascata senza interrompere l'esecuzione
                    return $query
                        ->when(
                            filled($data['date_from']),
                            fn (Builder $query) => $query->whereDate('pi_validation_date', '>=', $data['date_from'])
                        )
                        ->when(
                            filled($data['date_to']),
                            fn (Builder $query) => $query->whereDate('pi_validation_date', '<=', $data['date_to'])
                        );
                })
                ->columnSpan(12),
            SelectFilter::make('pi_validation_user_id')
                ->label('Validate da')
                ->placeholder('Tutti gli utenti')
                ->relationship('piValidationUser', 'name')
                ->searchable()
                ->columnSpan(6)
                ->preload(),
        ];
    }
}
