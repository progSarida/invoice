<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use Filament\Forms;

class NoteSection
{
    public static function make(): Forms\Components\Section
    {
        return  Forms\Components\Section::make('Note')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->collapsed(false)
            ->visible()
            ->schema([
                self::noteField(),
            ]);
    }

    private static function noteField(): Forms\Components\Textarea
    {
        return Forms\Components\Textarea::make('note')
            ->label('Note')
            ->rows(3)
            ->columnSpanFull();
    }
}
