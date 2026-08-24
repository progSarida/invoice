<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use App\Enums\PaymentMode;
use App\Enums\PaymentType;
use Filament\Forms;
use Filament\Forms\Components\Section;

class PaymentDataSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Dati per il pagamento')
            ->collapsed(false)
            ->columns(6)
            ->schema([
                Forms\Components\Select::make('payment_mode')
                    ->label('Condizioni di pagamento')
                    ->columnSpan(2)
                    ->options(
                        collect(PaymentMode::cases())
                            ->sortBy(fn (PaymentMode $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentMode $type) => [
                                $type->getCode() => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    //  ->disabled()
                    ,
                Forms\Components\Select::make('payment_type')
                    ->label('Metodo di pagamento')
                    ->columnSpan(2)
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->getCode() => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    //  ->disabled()
                    ,
                Forms\Components\DatePicker::make('payment_deadline')
                    ->label('Scadenza pagamento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2)
                    //  ->disabled()
                    ,

                Forms\Components\DatePicker::make('last_payment_date')
                    ->label('Data ultimo pagamento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(2)
                     ->disabled()
                    ,

                Forms\Components\TextInput::make('total_payment')
                    ->label('Totale pagato')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : number_format(0, 2, ',', '.'))
                    ->columnSpan(2)
                    // ->visible(fn (Get $get) => !is_null($get('bank')))
                     ->disabled()
                    ,

                Forms\Components\TextInput::make('bank')
                    ->label('Istituto finanziario')
                    ->columnSpan(3)
                    //  ->disabled()
                    ,
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN')
                    ->columnSpan(3)
                    // ->visible(fn (Get $get) => !is_null($get('iban')))
                    //  ->disabled()
                    ,
            ]);
    }
}
