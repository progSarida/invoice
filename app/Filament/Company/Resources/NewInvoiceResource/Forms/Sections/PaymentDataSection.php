<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Enums\PaymentMode;
use App\Enums\PaymentType;
use App\Models\BankAccount;
use App\Models\DocType;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentDataSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Dati per il pagamento')->columns(4)
            ->collapsed(false)
            ->columns(24)
            ->schema([
                Forms\Components\Select::make('bank_account_id')->label('IBAN')
                    ->relationship(
                        name: 'bankAccount',
                        modifyQueryUsing: fn (Builder $query) =>
                        $query->where('company_id',Filament::getTenant()->id)->orderBy('position', 'asc')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->name} $record->iban"
                    )
                    ->afterStateUpdated( function(Set $set, $state){
                        $bankAccount = BankAccount::find($state);
                        if($bankAccount->name == 'Giroconto'){
                            $set('is_total_with_vat', true);
                        }
                    })
                    ->live()
                    ->searchable()
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->columnSpan(10)
                    ->preload(),
                Forms\Components\Select::make('payment_mode')->label('Modalità')
                    // ->options(PaymentType::class)
                    ->afterStateUpdated( function(Set $set, $state){
                        if($state == PaymentMode::TP02->value){
                            $set('rate_number', 1);
                        }
                        else{
                            $set('rate_number', null);
                        }
                    })
                    ->reactive()
                    ->options(
                        collect(PaymentMode::cases())
                            ->sortBy(fn (PaymentMode $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentMode $type) => [
                                $type->value => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->default(PaymentMode::TP02->value)
                    ->columnSpan(6),
                Forms\Components\TextInput::make('rate_number')
                    ->label('Rate')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(3)
                    ->default(1)
                    ->required(fn(Get $get): bool => $get('payment_mode') != PaymentMode::TP02->value)
                    ->disabled(fn(Get $get): bool => $get('payment_mode') == PaymentMode::TP02->value)
                    ->dehydrated(),
                Forms\Components\Checkbox::make('is_total_with_vat')
                    ->label('Includi IVA')
                    ->columnSpan(3),
                Forms\Components\Select::make('payment_type')->label('Tipo')
                    // ->options(PaymentType::class)
                    ->options(
                        collect(PaymentType::cases())
                            ->sortBy(fn (PaymentType $type) => $type->getOrder())
                            ->mapWithKeys(fn (PaymentType $type) => [
                                $type->value => $type->getLabel()
                            ])
                            ->toArray()
                    )
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->default('mp05')
                    ->columnSpan(8),
                Forms\Components\Select::make('payment_days')
                    ->label('Giorni')
                    ->required(fn(Get $get) => DocType::find($get('doc_type_id'))?->name !== 'TD99')
                    ->options([
                        30 => '30',
                        60 => '60',
                        90 => '90',
                        120 => '120',
                    ])
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->default(30)
                    ->columnSpan(3),
                    ]);
    }
}
