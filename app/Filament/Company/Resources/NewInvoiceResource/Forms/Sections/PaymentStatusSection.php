<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Enums\PaymentStatus;
use Filament\Forms;
use Filament\Forms\Components\Section;

class PaymentStatusSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Stato del pagamento')->columns(2)
            ->collapsed()
            ->columns(6)
            ->schema([
                Forms\Components\Select::make('payment_status')->label('Status')
                    ->required()
                    ->default('waiting')
                    ->options(PaymentStatus::class)->columnSpan(2),

                Forms\Components\DatePicker::make('last_payment_date')->label('Data ultimo pagamento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->native(false)
                    ->displayFormat('d F Y')->columnSpan(2)->disabled(),
                Forms\Components\TextInput::make('total_payment')->label('Totale pagamenti')
                    ->extraInputAttributes(['style' => 'text-align: right;'])
                    ->columnSpan(2)
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    // ->numeric()
                    ->suffix('€')->columnSpan(1)->disabled(),

            ]);
    }
}
