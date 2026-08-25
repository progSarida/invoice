<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Tables\Filters;

use App\Enums\PaymentType;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentDataFilters
{
    public static function make(): array
    {
        return [
            SelectFilter::make('bank_account_id')
                ->label('Conto')
                ->relationship(
                    name: 'bankAccount',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) =>
                    $query->where('company_id',Filament::getTenant()->id)->orderBy('position', 'asc')
                )
                ->getOptionLabelFromRecordUsing(
                    fn (Model $record) => "{$record->name} {$record->iban}"
                )
                ->columnSpan(8),

            SelectFilter::make('payment_type')
                ->label('Metodo di pagamento')
                ->options(
                    collect(PaymentType::cases())
                        ->sortBy(fn (PaymentType $type) => $type->getOrder())
                        ->mapWithKeys(fn (PaymentType $type) => [
                            $type->value => $type->getLabel()                            // sulle fatture attive è salvato il value
                        ])
                        ->toArray()
                )
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }
                    return $query->where('invoices.payment_type', $data['value']);
                })
                ->columnSpan(6),
        ];
    }
}
