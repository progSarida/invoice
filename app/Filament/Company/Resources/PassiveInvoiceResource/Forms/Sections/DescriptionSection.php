<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use Filament\Forms;
use Filament\Forms\Components\Section;

class DescriptionSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Descrizione')
            ->collapsible()
            ->schema([
                Forms\Components\Textarea::make('description')
                    ->label('')
                    ->columnSpanFull()
                    //  ->disabled()
                    ,
            ]);
    }
}
