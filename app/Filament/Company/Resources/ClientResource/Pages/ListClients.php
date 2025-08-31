<?php

namespace App\Filament\Company\Resources\ClientResource\Pages;

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
            Actions\Action::make('compare')
                ->icon('carbon-compare')
                ->label('Comparata')
                ->tooltip('Stampa fatturazione comparata')
                ->color('primary')
                ->modalWidth('6xl')
                ->modalHeading('Fattura comparata')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            TextInput::make('accrual_year_1')
                                ->label('Anno competenza 1')
                                ->columnSpan(3)
                                ->required()
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1),
                            TextInput::make('accrual_year_2')
                                ->label('Anno competenza 2')
                                ->columnSpan(3)
                                ->required()
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1),
                            Select::make('doc_type_id')
                                ->label('Tipo documento')
                                ->columnSpan(6)
                                ->options(function () {
                                    $docs = \Filament\Facades\Filament::getTenant()
                                        ->docTypes()
                                        ->select('doc_types.id', 'doc_types.description')
                                        ->get();
                                    return $docs->pluck('description', 'id')->toArray();
                                })
                                ->searchable()
                                ->preload(),
                            Select::make('tax_type')
                                ->label('Entrata')
                                ->columnSpan(3)
                                ->options(TaxType::class)
                                ->searchable()
                                ->preload(),
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
                            Select::make('manage_type_id')
                                ->label('Tipo di gestione')
                                ->columnSpan(4)
                                ->options(function () {
                                    return ManageType::orderBy('order')->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload(),
                            TextInput::make('from_budget_year')
                                ->label('Anno bilancio da')
                                ->columnSpan(2)
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1),
                            TextInput::make('to_budget_year')
                                ->label('Anno bilancio a')
                                ->columnSpan(2)
                                ->numeric()
                                ->minValue(1900)
                                ->maxValue(date('Y') + 1),
                            DatePicker::make('from_invoice_date')
                                ->label('Data fatturazione da')
                                ->columnSpan(2),
                            DatePicker::make('to_invoice_date')
                                ->label('Data fatturazione a')
                                ->columnSpan(2),
                            Select::make('contract_type')
                                ->label('Tipo contratto')
                                ->options(ContractType::class)
                                ->searchable()
                                ->preload()
                                ->columnSpan(3),
                        ]),
                ])
                ->action(function ($data) {
                    // Recupero i dati dalla form
                    $clientId = $data['client_id'] ?? null;
                    $taxType = $data['tax_type'] ?? null;
                    $contractType = $data['contract_type'] ?? null;
                    $docTypeId = $data['doc_type_id'] ?? null;
                    $manageTypeId = $data['manage_type_id'] ?? null;
                    $accrualYear1 = $data['accrual_year_1'] ?? null;
                    $accrualYear2 = $data['accrual_year_2'] ?? null;
                    $fromBudgetYear = $data['from_budget_year'] ?? null;
                    $toBudgetYear = $data['to_budget_year'] ?? null;
                    $fromInvoiceDate = $data['from_invoice_date'] ?? null;
                    $toInvoiceDate = $data['to_invoice_date'] ?? null;

                    // query contratti
                    $contracts = \Filament\Facades\Filament::getTenant()                                
                        ->newContracts()
                        ->with('invoices')                                                                              // carico la relazione invoices
                        ->when($clientId, function ($query, $clientId) {
                            return $query->where('client_id', $clientId);                                               // filtro il cliente
                        })
                        ->when($taxType, function ($query, $taxType) {
                            return $query->where('tax_type', $taxType);                                                 // filtro l'entrata
                        })
                        ->when($contractType, function ($query, $contractType) {
                            return $query->whereHas('lastDetail', function ($query) use ($contractType) {
                                $query->where('contract_type', $contractType);                                          // filtro il tipo di contratto
                            });
                        })
                        ->get();

                    // query dati fatture
                    $param = $contracts->mapWithKeys(function ($contract) use ($docTypeId, $manageTypeId, $accrualYear1, $accrualYear2, $fromBudgetYear, $toBudgetYear, $fromInvoiceDate, $toInvoiceDate) {
                        $commonFilteredInvoices = $contract->invoices
                            ->when($docTypeId, function ($invoices, $docTypeId) {
                                return $invoices->where('doc_type_id', $docTypeId);                                     // filtro il  tipo di documento
                            })
                            ->when($manageTypeId, function ($invoices, $manageTypeId) {
                                return $invoices->where('manage_type_id', $manageTypeId);                               // filtro tipo di gestione
                            })
                            ->when($fromBudgetYear, function ($invoices, $fromBudgetYear) {
                                return $invoices->where('budget_year', '>=', (int)$fromBudgetYear);                     // filtro inizio anno bilancio
                            })
                            ->when($toBudgetYear, function ($invoices, $toBudgetYear) {
                                return $invoices->where('budget_year', '<=', (int)$toBudgetYear);                       // filtro fine anno bilancio
                            })
                            ->when($fromInvoiceDate, function ($invoices, $fromInvoiceDate) {
                                return $invoices->where('invoice_date', '>=', $fromInvoiceDate);                        // filtro inizio data fattura
                            })
                            ->when($toInvoiceDate, function ($invoices, $toInvoiceDate) {
                                return $invoices->where('invoice_date', '<=', $toInvoiceDate);                          // filtro fine data fattura
                            });

                        $invoicesYear1 = $commonFilteredInvoices->when($accrualYear1, function ($invoices, $accrualYear1) {
                            return $invoices->where('accrual_year', '=', (int)$accrualYear1);                           // filtro anno competenza 1
                        });
                        if ($invoicesYear1->isEmpty()) {                                                                // non ci sono fatture per l'anno 1
                            $year1 = [
                                'invoices' => [],
                                'total' => 0,
                                'payed' => 0,
                                'notes' => 0,
                            ];
                        } else {                                                                                        // dati fatture anno 1
                            $total1 = $invoicesYear1->sum(function ($invoice) use ($contract) {
                                return $contract->client && $contract->client->type === 'public'
                                    ? ($invoice->no_vat_total ?? 0)
                                    : ($invoice->total ?? 0);
                            });
                            $payed1 = $invoicesYear1->sum(function ($invoice) {
                                return ($invoice->total_payment ?? 0);
                            });
                            $notes1 = $invoicesYear1->sum(function ($invoice) {
                                return ($invoice->total_notes ?? 0);
                            });
                            $year1 = [
                                'invoices' => $invoicesYear1->toArray(),
                                'total' => $total1,
                                'payed' => $payed1,
                                'notes' => $notes1,
                            ];
                        }

                        $invoicesYear2 = $commonFilteredInvoices->when($accrualYear2, function ($invoices, $accrualYear2) {
                            return $invoices->where('accrual_year', '=', (int)$accrualYear2);                           // filtro anno competenza 2
                        });
                        if ($invoicesYear2->isEmpty()) {                                                                // non ci sono fatture per l'anno 2
                            $year2 = [
                                'invoices' => [],
                                'total' => 0,
                                'payed' => 0,
                                'notes' => 0,
                            ];
                        } else {                                                                                        // dati fatture anno 2
                            $total2 = $invoicesYear2->sum(function ($invoice) use ($contract) {
                                return $contract->client && $contract->client->type === 'public'
                                    ? ($invoice->no_vat_total ?? 0)
                                    : ($invoice->total ?? 0);
                            });
                            $payed2 = $invoicesYear2->sum(function ($invoice) {
                                return ($invoice->total_payment ?? 0);
                            });
                            $notes2 = $invoicesYear2->sum(function ($invoice) {
                                return ($invoice->total_notes ?? 0);
                            });
                            $year2 = [
                                'invoices' => $invoicesYear2->toArray(),
                                'total' => $total2,
                                'payed' => $payed2,
                                'notes' => $notes2,
                            ];
                        }

                        if ($invoicesYear1->isEmpty() && $invoicesYear2->isEmpty()) {
                            return [];                                                                                  // elimino i contratti che non hanno fatture per entrambi gli anni di competenza
                        }

                        return [
                            $contract->id => array_merge($contract->toArray(), [
                                'details' => $contract->lastDetail ? $contract->lastDetail->toArray() : [],
                                'client' => $contract->client,
                                'year1' => $year1,
                                'year2' => $year2,
                            ]),
                        ];
                    })->toArray();

                    dd($param);

                    return response()->streamDownload(function () use ($param, $data) {
                        echo Pdf::loadHTML(
                            Blade::render('pdf.compare', [
                                'data' => $param,                                                                       // dati da stampare
                                'filters' => $data,                                                                     // filtri
                            ])
                        )
                            ->setPaper('A4', 'landscape')
                            ->stream();
                    }, 'Fatturazione_Comparata.pdf');

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
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
