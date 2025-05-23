<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use Filament\Actions;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\ExportAction;
use Illuminate\Support\Collection;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Company\Resources\ClientResource;
use App\Filament\Exports\ClientExporter;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->keyBindings(['f6']),
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco clienti')
                // ->iconButton()                                                                  // mostro solo icona
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get();                       // recupero risultato della query
                    $filters = $livewire->tableFilters ?? [];                                   // recupero i filtri
                    $search = $livewire->tableSearch ?? null;                                   // recupero la ricerca

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            echo Pdf::loadHTML(
                                Blade::render('pdf.clients', [
                                    'clients' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->stream();
                        }, 'Clienti.pdf');

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
                ->exporter(ClientExporter::class)
        ];
    }
}
