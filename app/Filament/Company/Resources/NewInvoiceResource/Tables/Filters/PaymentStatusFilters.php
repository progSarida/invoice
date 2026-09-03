<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use App\Enums\SdiStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Set;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class PaymentStatusFilters
{
    public static function make(): array
    {
        return [
            // Riga 5
            SelectFilter::make('sdi_status')->label('Stato')->options(SdiStatus::class)
                ->multiple()->searchable()->preload()->columnSpan(['default' => 'full', 'lg' => 10]),
            SelectFilter::make('paid')
                ->label('Stato pagamento')
                ->placeholder('Tutti gli stati')
                ->options([
                    'si' => 'Pagate',
                    'par' => 'Parzialmente pagate',
                    'no' => 'Non pagate',
                ])
                // ->query(function (Builder $query, array $data): Builder {
                //     if (!isset($data['value'])) {
                //         return $query;
                //     }
                //     $sql = 'total - (total_payment + total_notes)';
                //     return $query->when($data['value'] === 'si', fn ($q) => $q->whereRaw("$sql <= 0"))
                //                 ->when($data['value'] === 'no', fn ($q) => $q->whereRaw("$sql > 0"));
                // })
                ->query(function (Builder $query, array $data): Builder {
                    if (!isset($data['value'])) {
                        return $query;
                    }

                    // totale di riferimento del documento: ivato o imponibile a seconda di is_total_with_vat
                    $total = '(CASE WHEN invoices.is_total_with_vat THEN COALESCE(total, 0) ELSE COALESCE(no_vat_total, 0) END)';
                    // quanto è già coperto fra pagamenti incassati e note di variazione
                    $covered = '(COALESCE(total_payment, 0) + COALESCE(total_notes, 0))';

                    return $query->where('parent_id', null) // Escludi quelle con parent_id se necessario
                        ->when($data['value'] === 'si', function ($q) use ($total, $covered) {
                            return $q->whereRaw("$total - $covered <= 0");
                        })->when($data['value'] === 'par', function ($q) use ($total, $covered) {
                            // qualcosa è stato incassato, ma resta del dovuto
                            return $q->whereRaw("COALESCE(total_payment, 0) > 0 AND $total - $covered > 0");
                        })->when($data['value'] === 'no', function ($q) use ($total, $covered) {
                            // nessun incasso e note che non coprono il dovuto
                            return $q->whereRaw("COALESCE(total_payment, 0) = 0 AND $total - $covered > 0");
                        });
                })
                ->columnSpan(['default' => 'full', 'lg' => 6])
                ->preload(),
            Filter::make('datePayment')
                ->columns(2)
                ->form([
                    DatePicker::make('date_from')
                        ->label('Data pagamento da')
                        ->extraInputAttributes(['class' => 'text-center'])
                        ->live(debounce: 1000) // <--- Fondamentale per attivare afterStateUpdated
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $set('date_to', $state);
                            }
                        }),
                    DatePicker::make('date_to')
                        ->label('Data pagamento a')
                        ->extraInputAttributes(['class' => 'text-center']),
                ])
                // ->query(function (Builder $query, array $data): Builder {
                //     // Modifichiamo la query per applicare i filtri in cascata senza interrompere l'esecuzione
                //     return $query
                //         ->when(
                //             filled($data['date_from']),
                //             // fn (Builder $query) => $query->whereHas('activePayments', function ($q) use ($data) {
                //             //     $q->whereDate('payment_date', '>=', $data['date_from']);
                //             // })
                //             fn (Builder $query) => $query->whereDate('last_payment_date', '>=', $data['date_from'])
                //         )
                //         ->when(
                //             filled($data['date_to']),
                //             // fn (Builder $query) => $query->whereHas('activePayments', function ($q) use ($data) {
                //             //     $q->whereDate('payment_date', '<=', $data['date_to']);
                //             // })
                //             fn (Builder $query) => $query->whereDate('last_payment_date', '<=', $data['date_to'])
                //         );
                // })
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['date_from']),
                            fn (Builder $query) => $query->where(function (Builder $query) use ($data) {
                                $query->where(function (Builder $q) use ($data) {
                                    $q->whereNotNull('flow')
                                        ->whereDate('last_payment_date', '>=', $data['date_from']);
                                })->orWhere(function (Builder $q) use ($data) {
                                    $q->whereNull('flow')
                                        ->whereHas('activePayments', function ($subQ) use ($data) {
                                            $subQ->whereDate('payment_date', '>=', $data['date_from']);
                                        });
                                });
                            })
                        )
                        ->when(
                            filled($data['date_to']),
                            fn (Builder $query) => $query->where(function (Builder $query) use ($data) {
                                $query->where(function (Builder $q) use ($data) {
                                    $q->whereNotNull('flow')
                                        ->whereDate('last_payment_date', '<=', $data['date_to']);
                                })->orWhere(function (Builder $q) use ($data) {
                                    $q->whereNull('flow')
                                        ->whereHas('activePayments', function ($subQ) use ($data) {
                                            $subQ->whereDate('payment_date', '<=', $data['date_to']);
                                        });
                                });
                            })
                        );
                })
                ->columnSpan(['default' => 'full', 'lg' => 8]),
        ];
    }
}
