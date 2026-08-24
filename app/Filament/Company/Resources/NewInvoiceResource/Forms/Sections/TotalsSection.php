<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Components\Section;

class TotalsSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Totali')->columns(4)
            ->columns(25)
            ->schema([
                // Valori di sola lettura: rispecchiano il record salvato, come le omonime colonne in tabella
                Forms\Components\TextInput::make('total')->label('Totale ivato')
                    ->extraInputAttributes(['style' => 'text-align: right;'])
                    ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format($state, 2, ',', '.') : null)
                    ->suffix('€')
                    ->columnSpan(5)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('tot_own')->label('Totale a doversi')
                    ->extraInputAttributes(['style' => 'text-align: right;'])
                    ->formatStateUsing(fn (?Invoice $record): ?string => $record
                        ? number_format($record->docType?->name == 'TD04' ? 0.00 : $record->getOwned(), 2, ',', '.')
                        : null)
                    ->suffix('€')
                    ->columnSpan(5)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('total_not')->label('Note di credito')
                    ->extraInputAttributes(['style' => 'text-align: right;'])
                    ->formatStateUsing(fn (?Invoice $record): ?string => $record
                        ? number_format($record->total_notes, 2, ',', '.')
                        : null)
                    ->suffix('€')
                    ->columnSpan(5)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('total_pay')->label('Pagamenti')
                    ->extraInputAttributes(['style' => 'text-align: right;'])
                    ->formatStateUsing(fn (?Invoice $record): ?string => $record
                        ? number_format($record->total_payment, 2, ',', '.')
                        : null)
                    ->suffix('€')
                    ->columnSpan(5)
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('tot_res')->label('Residuo')
                    ->extraInputAttributes(['style' => 'text-align: right;'])
                    ->formatStateUsing(fn (?Invoice $record): ?string => $record
                        ? number_format($record->docType?->name == 'TD04' ? 0.00 : $record->getResidue(), 2, ',', '.')
                        : null)
                    ->suffix('€')
                    ->columnSpan(5)
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
