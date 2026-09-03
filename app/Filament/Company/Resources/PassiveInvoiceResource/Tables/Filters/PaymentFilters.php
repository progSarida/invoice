<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Tables\Filters;

use App\Enums\PaymentType;
use App\Models\BankAccount;
use App\Models\PassiveInvoice;
use Filament\Facades\Filament;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class PaymentFilters
{
    public static function make(): array
    {
        return [
            SelectFilter::make('paid')
                ->label('Pagamento')
                ->columnSpan(['default' => 'full', 'lg' => 3])
                ->options([
                    'si' => 'Totale',
                    'par' => 'Parziale',
                    'no' => 'Nessuno',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (!isset($data['value'])) {
                        return $query;
                    }
                    $tolerance = PassiveInvoice::paymentTolerance();                                        // tolleranza sul residuo dell'azienda di sessione
                    $query->where('doc_type', '!=', 'TD04');                                                // escludo le note di credito
                    return $query->when($data['value'] === 'si', fn ($q) => $q->paid($tolerance))
                                ->when($data['value'] === 'par', fn ($q) => $q->partiallyPaid($tolerance))
                                ->when($data['value'] === 'no', fn ($q) => $q->notPaid());
                })
                ->indicateUsing(function (array $data): ?string {
                    if (! isset($data['value'])) {
                        return null;
                    }
                    $labels = ['si' => 'Totale', 'par' => 'Parziale', 'no' => 'Nessuno'];
                    $indicator = 'Pagamento: ' . ($labels[$data['value']] ?? $data['value']);
                    $tolerance = PassiveInvoice::paymentTolerance();
                    if ($tolerance > 0 && $data['value'] !== 'no') {
                        $indicator .= ' (tolleranza ' . number_format($tolerance, 2, ',', '.') . ' €)';
                    }
                    return $indicator;
                })
                ->preload(),
            SelectFilter::make('bank_account')
                ->label('Conto di debito')
                ->options(fn (): array => BankAccount::query()
                    ->where('company_id', Filament::getTenant()->id)
                    ->orderBy('position', 'asc')
                    ->get()
                    ->mapWithKeys(fn (BankAccount $account): array => [$account->id => "{$account->name} {$account->iban}"])
                    ->all())
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }
                    // la fattura passiva non ha il conto: filtro su quello dei pagamenti collegati
                    return $query->whereHas(
                        'passivePayments',
                        fn (Builder $q): Builder => $q->where('bank_account_id', $data['value'])
                    );
                })
                ->columnSpan(['default' => 'full', 'lg' => 8]),
            SelectFilter::make('payment_type')
                ->label('Metodo di pagamento')
                ->options(
                    collect(PaymentType::cases())
                        ->sortBy(fn (PaymentType $type) => $type->getOrder())
                        ->mapWithKeys(fn (PaymentType $type) => [
                            $type->getCode() => $type->getLabel()                        // sulle fatture passive è salvato il codice
                        ])
                        ->toArray()
                )
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }
                    return $query->where('passive_invoices.payment_type', $data['value']);
                })
                ->columnSpan(['default' => 'full', 'lg' => 7]),
        ];
    }
}
