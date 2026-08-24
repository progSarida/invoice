<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Enums\TimingType;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;

class BillingTimingSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('')
            ->columns(6)
            ->schema([
                Forms\Components\Select::make('timing_type')->label('Modalità di fatturazione')->options(TimingType::class)
                    ->required(fn (Get $get) => $get('timing_type') == 'differita')
                    ->placeholder(null)
                    ->default('contestuale')
                    ->live()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('delivery_note')->label('Documento di trasporto')
                    ->required(fn (Get $get) => $get('timing_type') == 'differita')
                    ->columnSpan(2)->disabled(fn (Get $get) => $get('timing_type') != 'differita'),
                Forms\Components\DatePicker::make('delivery_date')->label('Data documento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->required(fn (Get $get) => $get('timing_type') == 'differita')
                    ->columnSpan(2)->disabled(fn (Get $get) => $get('timing_type') != 'differita')
                    ->native(false)
                    ->displayFormat('d F Y'),
            ]);
    }
}
