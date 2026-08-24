<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use App\Models\PassiveInvoice;
use Filament\Forms;
use Filament\Forms\Components\Section;

class TotalsSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Totali')
            ->collapsed(false)
            ->columns(8)
            ->schema([
                // Valori di sola lettura: rispecchiano il documento salvato, come le omonime colonne in tabella
                Forms\Components\TextInput::make('total')
                    ->label('Dovuto')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn ($state): ?string => number_format((float) $state, 2, ',', '.'))
                    ->suffix('€')
                    ->columnSpan(2)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('total_not')
                    ->label('Note di variazione')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn (?PassiveInvoice $record): ?string => $record
                        ? number_format($record->getNotesTotal(), 2, ',', '.')
                        : 0.00)
                    ->suffix('€')
                    ->columnSpan(2)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('total_pay')
                    ->label('Pagamenti')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn (?PassiveInvoice $record): ?string => $record
                        ? number_format((float) $record->total_payment, 2, ',', '.')
                        : 0.00)
                    ->suffix('€')
                    ->columnSpan(2)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('residue')
                    ->label('Residuo')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn (?PassiveInvoice $record): ?string => $record
                        ? number_format($record->getResidue(), 2, ',', '.')
                        : 0.00)
                    ->suffix('€')
                    ->columnSpan(2)
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
