<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use App\Filament\Company\Resources\NewContractResource;
use App\Filament\Exports\NewContractExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Blade;



class ListNewContracts extends ListRecords
{
    protected static string $resource = NewContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco contratti')
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get();                           // Recupero risultato della query
                    $filters = $livewire->tableFilters ?? [];                                       // Recupero i filtri
                    $search = $livewire->tableSearch ?? null;                                       // Recupero la ricerca

                    $fileName = 'Contratti_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            echo Pdf::loadHTML(
                                Blade::render('pdf.new_contracts', [
                                    'contracts' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->stream();
                        }, $fileName);

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                })
                ,
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color('primary')
                ->exporter(NewContractExporter::class)
        ];
    }
}
