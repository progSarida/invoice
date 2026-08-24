<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use Filament\Forms;
use Filament\Forms\Components\Section;

class SdiStatusSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Status SDI')
                ->collapsed(false)
                ->columns(6)
                ->schema([
                    Forms\Components\TextInput::make('sdi_status')
                        ->label('Status')
                        ->columnSpan(3)
                        //  ->disabled()
                        ,
                    Forms\Components\TextInput::make('sdi_code')
                        ->label('Codice SDI')
                        ->columnSpan(3)
                        //  ->disabled()
                        ,
                ]);
    }
}
