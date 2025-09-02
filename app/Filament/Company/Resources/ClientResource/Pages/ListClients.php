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
use Filament\Forms\Components\Checkbox;
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
                ->icon('tabler-report-search')
                ->label('Partitario')
                ->tooltip('Stampa partitario clienti')
                ->color('primary')
                ->modalWidth('5xl')
                ->modalHeading('Partitario')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            Select::make('client_id')
                                ->label('Cliente')
                                ->columnSpan(5)
                                ->options(function () {
                                    $docs = \Filament\Facades\Filament::getTenant()->clients()->select('clients.id', 'clients.denomination')->get();
                                    return $docs->pluck('denomination', 'id')->toArray();
                                })
                                ->searchable('denomination')
                                ->preload()
                                ->optionsLimit(5),
                            DatePicker::make('from_date')
                                ->label('Da data')
                                ->columnSpan(2),
                            DatePicker::make('to_date')
                                ->label('A data')
                                ->columnSpan(2),
                            Checkbox::make('prec_residue')
                                ->label('Con residuo precedente')
                                ->columnSpan(3)
                                ->default(false)
                        ]),
                ])
                ->action(function ($data) {
                    // dd($data);
                    $clientId = $data['client_id'] ?? null;
                    $fromDate = $data['from_date'] ?? null;
                    $toDate = $data['to_date'] ?? null;
                    $precResidue = $data['prec_residue'] ?? null;

                    $invoices = \Filament\Facades\Filament::getTenant()
                        ->invoices()
                        ->with([
                            'activePayments' => function ($query) use ($fromDate, $toDate) {
                                $query->when($fromDate, fn($q) => $q->where('payment_date', '>=', $fromDate))
                                    ->when($toDate, fn($q) => $q->where('payment_date', '<=', $toDate));
                            },
                            'docType',
                            'invoice',
                        ])
                        ->when($clientId, fn($q) => $q->where('client_id', $clientId))
                        ->when($fromDate, fn($q) => $q->whereHas('invoices', fn($q) => $q->where('invoice_date', '>=', $fromDate)))
                        ->when($toDate, fn($q) => $q->whereHas('lastDetail', fn($q) => $q->where('invoice_date', '<=', $toDate)))
                        ->get();
                    dd($invoices);
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
