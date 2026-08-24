<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;

class DescriptionsSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Descrizioni')
            ->collapsible()
            ->schema([
                // Forms\Components\Textarea::make('description')->label("Descrizione (Composizione automatica 'variabile dall'operatore', con inserimento dei campi 'Anno di bilancio', 'Descrizione da riportare in fattura' (presente nel dettaglio del contratto), Riferimento, 'Da data' e 'A data', oppure 'Dal numero', 'Al numero' e 'Totali')")
                Forms\Components\Textarea::make('description')->label('Descrizione')
                    ->required()
                    ->live()
                    ->hintIcon('heroicon-o-information-circle', tooltip: "Composizione automatica 'variabile dall'operatore', con inserimento dei campi 'Anno di bilancio', 'Descrizione da riportare in fattura' (presente nel dettaglio del contratto), Riferimento, 'Da data' e 'A data', oppure 'Dal numero', 'Al numero' e 'Totali'")
                    ->afterStateUpdated(function ($state) {
                        if (! preg_match('/\(ab\d{2}\)/', $state)) {
                            Notification::make()
                                ->title("Errore! La descrizione deve contenere il riferimento all'anno di bilancio nel formato (ab**)")
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if (! preg_match('/\(ab\d{2}\)/', $value)) {
                                $fail("La descrizione deve contenere i riferimenti all'anno di bilancio nel formato (ab**)");
                            }
                        },
                    ])
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('free_description')->label('Note interne operatore (non verranno mostrate in fattura)')
                    // ->required()
                    ->columnSpanFull(),
            ]);
    }
}
