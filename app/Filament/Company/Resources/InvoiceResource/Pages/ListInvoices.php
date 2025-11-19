<?php

namespace App\Filament\Company\Resources\InvoiceResource\Pages;

use Filament\Actions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use App\Filament\Exports\InvoiceExporter;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Company\Resources\InvoiceResource;
use Filament\Actions\ExportAction;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco clienti')
                // ->iconButton() // mostro solo icona
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                                Blade::render('pdf.invoices', [
                                    'invoices' => $records,
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
                        }, 'Clienti.pdf');

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                }),
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color('primary')
                ->exporter(InvoiceExporter::class)
        ];
    }
}
