<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Enums\SdiStatus;
use Filament\Forms;
use Filament\Forms\Components\Section;

class SdiStatusSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Stato SDI')->columns(2)
            ->collapsed()
            ->columns(6)
            ->schema([
                Forms\Components\Select::make('sdi_status')->label('Ultimo stato')->options(SdiStatus::class)
                    ->default(SdiStatus::DA_INVIARE)
                    // ->disabled(fn ($state) => !in_array($state, ['rifiutata', 'scartata', 'mancata_consegna']))
                    ->disabled(fn ($state) => match(true) {
                        $state instanceof SdiStatus => $state->lockChange(),
                        $state !== null => SdiStatus::tryFrom($state)?->lockChange() ?? true,
                        default => true,
                    })
                    ->columnSpan(2),
                Forms\Components\TextInput::make('sdi_code')->label('Codice SdI')->readOnly()->columnSpan(2)->disabled(),
                Forms\Components\DatePicker::make('sdi_date')->label('Data')
                    ->extraInputAttributes(['class' => 'text-center'])->readOnly()->columnSpan(2)->disabled()
                    ->native(false)
                    ->displayFormat('d F Y'),
                Forms\Components\TextArea::make('sdi_info')->label('Info')
                    ->readOnly()
                    ->columnSpan(6)
                    ->disabled()
            ]);
    }
}
