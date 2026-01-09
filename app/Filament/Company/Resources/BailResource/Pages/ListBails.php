<?php

namespace App\Filament\Company\Resources\BailResource\Pages;

use App\Filament\Company\Resources\BailResource;
use App\Filament\Exports\BailExporter;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListBails extends ListRecords
{
    protected static string $resource = BailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco fatture')
                // ->iconButton() // mostro solo icona
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    $fileName = 'Polizze_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                                Blade::render('pdf.bails', [
                                    'bails' => $records,
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
                })
                // ->keyBindings(['alt+s'])
                ,
            // ExportAction::make('esporta')
            //     ->icon('phosphor-export')
            //     ->label('Esporta')
            //     ->color(Color::rgb('rgb(0, 153, 0)'))
            //     ->exporter(BailExporter::class)
            //     // ->keyBindings(['alt+e'])
            //     ,
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color(Color::rgb('rgb(0, 153, 0)'))
                ->exporter(BailExporter::class)
                ->before(function ($livewire) {
                    // Recupera i filtri attivi dalla tabella
                    $filters = $livewire->getTableFilters();

                    // Imposta la data se il filtro è attivo
                    if (isset($filters['active_at_date']['selected_date'])) {
                        BailExporter::$activeAtDate = $filters['active_at_date']['selected_date'];
                    } else {
                        BailExporter::$activeAtDate = null;
                    }
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
