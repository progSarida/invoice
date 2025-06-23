<?php

namespace App\Filament\Company\Resources\NewActivePaymentsResource\Pages;

use App\Filament\Company\Resources\NewActivePaymentsResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Blade;

class ListNewActivePayments extends ListRecords
{
    protected static string $resource = NewActivePaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco pagamenti')
                // ->iconButton() // mostro solo icona
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    $fileName = 'Pagamenti_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                                Blade::render('pdf.new_active_payments', [
                                    'payments' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->setOptions([
                                    'isHtml5ParserEnabled' => true, // Abilita parser HTML5 per CSS avanzato
                                    'isPhpEnabled' => true, // Abilita PHP nel template
                                    'isFontSubsettingEnabled' => true, // Ottimizza i font
                                ]);

                            echo $pdf->stream();
                        }, $fileName);

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                }),
        ];
    }
}
