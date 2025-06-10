<?php

namespace App\Filament\Company\Resources\NewContractResource\Pages;

use Filament\Actions;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\ExportAction;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Exports\NewContractExporter;
use App\Filament\Company\Resources\NewContractResource;

class ListNewContracts extends ListRecords
{
    protected static string $resource = NewContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            // Actions\Action::make('stampa')
            //     ->icon('heroicon-o-printer')
            //     ->label('Stampa')
            //     ->tooltip('Stampa elenco contratti')
            //     ->color('primary')
            //     ->action(function ($livewire) {
            //         $records = $livewire->getFilteredTableQuery()->get();                       // recupero risultato della query
            //         $filters = $livewire->tableFilters ?? [];                                   // recupero i filtri
            //         $search = $livewire->tableSearch ?? null;                                   // recupero la ricerca

            //         return response()
            //             ->streamDownload(function () use ($records, $search, $filters) {
            //                 echo Pdf::loadHTML(
            //                     Blade::render('pdf.new_contracts', [
            //                         'clients' => $records,
            //                         'search' => $search,
            //                         'filters' => $filters,
            //                     ])
            //                 )
            //                     ->setPaper('A4', 'landscape')
            //                     ->stream();
            //             }, 'Contratti.pdf');

            //         Notification::make()
            //             ->title('Stampa avviata')
            //             ->success()
            //             ->send();
            //     })
            //     ,
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color('primary')
                ->exporter(NewContractExporter::class)
        ];
    }
}
