<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

use App\Enums\ClientType;
use App\Enums\ContractType;
use App\Enums\TaxType;
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
use App\Models\ManageType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->tooltip('Crea nuovo cliente')
                // ->keyBindings(['alt+n']),
                ->keyBindings(['f6']),
            Actions\Action::make('print')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco clienti')
                // ->iconButton()                                                                                       // mostro solo icona
                ->color('primary')
                // ->keyBindings(['alt+s'])
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get();                                               // recupero risultato della query
                    $filters = $livewire->tableFilters ?? [];                                                           // recupero i filtri
                    $search = $livewire->tableSearch ?? null;
                    // recupero la ricerca

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
                }),
                Actions\Action::make('ledger')
                ->icon('carbon-compare')
                ->label('Comparata')
                ->tooltip('Stampa fatturazione comparata')
                ->color('primary')
                ->modalWidth('6xl')
                ->modalHeading('Fattura comparata')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            //
                        ]),
                ])
                ->action(function ($data) {
                    //
                }),
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->tooltip('Esporta elenco clienti')
                ->color('primary')
                ->exporter(ClientExporter::class)
                // ->keyBindings(['alt+e'])
        ];
    }
}
