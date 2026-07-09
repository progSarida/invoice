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
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ManageType;
use DateTime;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Support\Colors\Color;
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
                ->color(Color::rgb('rgb(255, 0, 0)'))
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
                // ->color('primary')
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->modalWidth('5xl')
                ->modalHeading('Stampa partitario')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            Select::make('client_id')
                                ->label('Cliente')
                                ->placeholder('Tutti')
                                ->columnSpan(6)
                                ->getSearchResultsUsing(function (string $search) {
                                    // Rimuovi spazi multipli e trim
                                    $search = trim(preg_replace('/\s+/', ' ', $search));

                                    $query = Client::query();

                                    // Cerca separatori (spazio, virgola, slash, trattino)
                                    $parts = preg_split('/[\s,\/\-]+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                                    if (count($parts) >= 2) {
                                        // Cerca ogni "parte" all'interno del campo denomination
                                        $query->where(function ($q) use ($parts) {
                                            foreach ($parts as $part) {
                                                $q->where('denomination', 'LIKE', "%{$part}%");
                                            }
                                        });
                                    } elseif (count($parts) === 1) {
                                        $value = $parts[0];
                                        $query->where(function ($q) use ($value) {
                                            $q->where('denomination', 'LIKE', "%{$value}%");
                                        });
                                    }

                                    // Esegui la query e mappatura
                                    return $query
                                        ->orderBy('denomination', 'asc')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($record) {
                                            $subtype = $record->subtype->getLabel() ?? 'Cliente sconosciuto';
                                            $denomination = $record->denomination ?? 'N/A';
                                            // $label = strtoupper("{$subtype}") . " - $denomination";
                                            $label = $denomination;

                                            return [$record->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function (?int $value) {
                                    if (!$value) { return null; }
                                    $record = Client::find($value);
                                    if (!$record) { return null; }
                                    // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                                    return $record->denomination;
                                })
                                ->getOptionLabelFromRecordUsing(
                                    // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                    fn (Model $record) => $record->denomination
                                )
                                // ->options(function () {
                                //     $docs = \Filament\Facades\Filament::getTenant()->clients()->select('clients.id', 'clients.denomination')->get();
                                //     return $docs->pluck('denomination', 'id')->toArray();
                                // })
                                ->live()
                                ->searchable('denomination')
                                ->preload()
                                ->optionsLimit(5),
                            DatePicker::make('from_date')
                                ->label('Da data')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->default(now()->startOfYear())
                                ->columnSpan(3),
                            DatePicker::make('to_date')
                                ->label('A data')
                                ->extraInputAttributes(['class' => 'text-center'])
                                ->default(today())
                                ->columnSpan(3),
                            Radio::make('type')
                                ->label('Partitario per')
                                ->options([
                                    'accrual' => 'Competenza',
                                    'year' => 'Esercizio',
                                ])
                                ->default('accrual')
                                ->inline()
                                ->columnSpan(5),
                            Checkbox::make('prec_residue')
                                ->label('Con residuo precedente')
                                ->columnSpan(4)
                                ->default(true),
                            Select::make('output_format')
                                ->label('Formato di output')
                                ->options([
                                    'pdf' => 'PDF',
                                    'excel' => 'Excel',
                                ])
                                ->default('pdf')
                                ->columnSpan(3),
                        ]),
                ])
                ->action(function ($data) {
                    return $this->printLedger($data);
                }),
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->tooltip('Esporta elenco clienti')
                ->color(Color::rgb('rgb(0, 153, 0)'))
                ->exporter(ClientExporter::class)
                // ->keyBindings(['alt+e'])
        ];
    }

    private function printLedger($data)
    {
        // dd($data);
        $input = [];
        $input['client_id'] = $clientId = $data['client_id'] ?? null;
        $input['from_date'] = $fromDate = $data['from_date'] ?? null;
        $input['to_date'] = $toDate = $data['to_date'] ?? null;
        $input['type'] = $type = $data['type'];
        $input['prec_residue'] = $precResidue = $data['prec_residue'];
        $input['output_format'] = $outputFormat = $data['output_format'] ?? 'pdf';

        $residue = $this->getPrecResidue($input);                                                            // residuo precedente
        $saldo = $input['prec_residue'] ? $residue : 0;                                                               // saldo iniziale

        if ($type === 'accrual') {
            $param = $this->createAccrualLedger($input);
        } elseif ($type === 'year') {
            $param = $this->createYearLedger($input);
        } else {
            throw new \InvalidArgumentException("Tipo di partitario non valido: {$type}");
        }

        // $originalParam = $param;                                                                            //
        // $duplicates = 15;                                                                                   //
        // $param = [];                                                                                        //
        // $index = 0;                                                                                         //
        // foreach ($originalParam as $item) {                                                                 //
        //     for ($i = 0; $i < $duplicates; $i++) {                                                          // duplicazione elementi
        //         $param[$index] = $item;                                                                     // usata per test
        //         $param[$index]['order'] = $item['order'] + ($i * 86400000);                                 //
        //         $param[$index]['reg'] = \Carbon\Carbon::parse($item['reg'])->addDays($i)->format('d/m/Y');  //
        //         $index++;                                                                                   //
        //     }                                                                                               //
        // }                                                                                                   //

        usort($param, function ($a, $b) {                                                                   // ordino gli elementi per data di registrazione
            return $a['order'] <=> $b['order'];
        });

        // $saldo = $precResidue ? $residue : 0;
        foreach ($param as &$item) {                                                                        // creo la colonna del saldo
            $saldo += $item['dare'];
            $saldo -= $item['avere'];
            $item['saldo'] = $saldo;
        }

        $temp = $param;
        $param = $this->closeOpen($data, $temp);                                                            // inserimento 'chiusura/apertura'

        // dd($param);

        $tenant = \Filament\Facades\Filament::getTenant();

        if ($outputFormat === 'excel') {
            return $this->generateExcelOutput($data, $residue, $param, $tenant);
        } else {
            return $this->generatePdfOutput($data, $residue, $param, $tenant);
        }
    }

    private function createAccrualLedgerOld($input): array
    {
        $invoices = \Filament\Facades\Filament::getTenant()
            ->invoices()
            ->with([
                'activePayments' => function ($query) use ($input) {
                    $query->when($input['from_date'], fn($q) => $q->where('payment_date', '>=', $input['from_date']))
                        ->when($input['to_date'], fn($q) => $q->where('payment_date', '<=', $input['to_date']));
                },
                'docType',
                'invoice',
            ])
            ->whereHas('docType', fn($q) => $q->whereIn('name', ['TD01', 'TD04', 'TD99']))
            ->when($input['client_id'], fn($q) => $q->where('client_id', $input['client_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('invoices.year', 'asc')
            ->orderBy('invoices.sectional_id', 'asc')
            ->orderBy('invoices.number', 'asc')
            ->get();
        // dd($invoices);

        $param = [];
        $residue = $this->getPrecResidue($input);                                                            // residuo precedente
        $saldo = $input['prec_residue'] ? $residue : 0;                                                               // saldo iniziale
        $index = 0;
        foreach($invoices as $key => $invoice) {
            // $param[$index]['order'] = \Carbon\Carbon::parse($invoice->created_at)->valueOf();
            $param[$index]['order'] = \Carbon\Carbon::parse($invoice->invoice_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            $param[$index]['cliente']['nome'] = $invoice->client->denomination;
            $param[$index]['cliente']['pi'] = $invoice->client->vat_code;
            $param[$index]['cliente']['cf'] = $invoice->client->tax_code;
            $param[$index]['num_doc'] = $invoice->invoiceNumber();
            $param[$index]['data_doc'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            $param[$index]['desc'] = $invoice->description;
            // $notRound = BankAccount::find($invoice?->bank_account_id)?->name != 'Giroconto';
            // $amount = ($invoice->client?->type?->value == 'public' && $notRound) ? $invoice->no_vat_total : $invoice->total;
            $amount = $invoice->is_total_with_vat ? $invoice->total : $invoice->no_vat_total;
            switch($invoice->docType->name) {
                case 'TD00':                                                                                // fattura
                    // $saldo += $amount;
                    $param[$index]['desc'] = 'Preavviso<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                case 'TD01':                                                                                // fattura
                    // $saldo += $amount;
                    $param[$index]['desc'] = 'Ns. Fattura<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                case 'TD02':                                                                                // acconti/anticipi su fattura
                    // $saldo -= $amount;
                    $param[$index]['desc'] = 'Acconto<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                case 'TD03':                                                                                // acconti/anticipi su parcella
                    // $saldo -= $amount;
                    $param[$index]['desc'] = 'Acconto su parcella<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                case 'TD04':                                                                                // nota di credito
                    // $saldo -= $amount;
                    $param[$index]['desc'] = 'N.C. su ' . $invoice->invoice?->invoiceNumber() . '<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                case 'TD99':                                                                                // fattura
                    // $saldo += $amount;
                    $param[$index]['desc'] = 'Quadratura<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                default:                                                                                    // tutta gli altri tipi di documento
                    // $saldo += $amount;
                    $param[$index]['desc'] = $invoice->description;
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
            }
            if($invoice->activePayments) {
                foreach($invoice->activePayments as $payment){
                    $index++;
                    // $param[$index]['order'] = \Carbon\Carbon::parse($payment->created_at)->valueOf();
                    $param[$index]['order'] = \Carbon\Carbon::parse($payment->payment_date)->valueOf();
                    $param[$index]['reg'] = \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y');
                    // $param[$index]['reg'] = \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y');
                    $param[$index]['cliente']['nome'] = $payment->invoice->client->denomination;
                    $param[$index]['cliente']['pi'] = $payment->invoice->client->vat_code;
                    $param[$index]['cliente']['cf'] = $payment->invoice->client->tax_code;
                    $param[$index]['num_doc'] = $payment->invoice->invoiceNumber();
                    $param[$index]['data_doc'] = $payment->invoice->invoice_date->format('d/m/Y');
                    $param[$index]['desc'] = 'S/DO FATTURA ' . strtoupper($payment->invoice->client->denomination) . '<br>Doc. orig. ' . $payment->invoice->invoiceNumber();
                    // $saldo -= $payment->amount;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $payment->amount ?? 0;
                    // $param[$index]['saldo'] = $saldo;
                }
            }
            $index++;
        }
        return $param;
    }

    private function createAccrualLedger($input): array
    {
        $param = [];
        $index = 0;
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture e note di credito con invoice_date nel periodo
        $invoices = $tenant
            ->invoices()
            ->with('docType')
            ->whereNull('parent_id')
            ->whereHas('docType', fn($q) => $q->whereIn('name', ['TD01', 'TD99']))
            ->when($input['client_id'], fn($q) => $q->where('client_id', $input['client_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('invoices.year', 'asc')
            ->orderBy('invoices.sectional_id', 'asc')
            ->orderBy('invoices.number', 'asc')
            ->get();

        foreach ($invoices as $invoice) {
            $amount = $invoice->is_total_with_vat ? $invoice->total : $invoice->no_vat_total;

            $param[$index]['order'] = \Carbon\Carbon::parse($invoice->invoice_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            $param[$index]['cliente']['nome'] = $invoice->client->denomination;
            $param[$index]['cliente']['pi'] = $invoice->client->vat_code;
            $param[$index]['cliente']['cf'] = $invoice->client->tax_code;
            $param[$index]['num_doc'] = $invoice->invoiceNumber();
            $param[$index]['data_doc'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');

            switch ($invoice->docType->name) {
                case 'TD01':
                    $param[$index]['desc'] = 'Ns. Fattura<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    break;
                case 'TD99':                                                                                // fattura
                    // $saldo += $amount;
                    $param[$index]['desc'] = 'Quadratura<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    // $param[$index]['saldo'] = $saldo;
                    break;
                default:
                    break;
            }

            if($invoice->creditNotes) {
                foreach($invoice->creditNotes as $note){
                    $amount = $note->is_total_with_vat ? $note->total : $note->no_vat_total;
                    $index++;
                    $param[$index]['order'] = \Carbon\Carbon::parse($note->invoice_date)->valueOf();
                    $param[$index]['reg'] = \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y');
                    $param[$index]['cliente']['nome'] = $note->client->denomination;
                    $param[$index]['cliente']['pi'] = $note->client->vat_code;
                    $param[$index]['cliente']['cf'] = $note->client->tax_code;
                    $param[$index]['num_doc'] = $note->invoiceNumber();
                    $param[$index]['data_doc'] = \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y');
                    $param[$index]['desc'] = 'N.C. su ' . $note->invoice?->invoiceNumber() . '<br>Doc. orig. ' . $note->invoiceNumber();
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                }
            }

            if($invoice->activePayments) {
                foreach($invoice->activePayments as $payment){
                    $index++;
                    // $param[$index]['order'] = \Carbon\Carbon::parse($payment->created_at)->valueOf();
                    $param[$index]['order'] = \Carbon\Carbon::parse($payment->payment_date)->valueOf();
                    $param[$index]['reg'] = \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y');
                    // $param[$index]['reg'] = \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y');
                    $param[$index]['cliente']['nome'] = $payment->invoice->client->denomination;
                    $param[$index]['cliente']['pi'] = $payment->invoice->client->vat_code;
                    $param[$index]['cliente']['cf'] = $payment->invoice->client->tax_code;
                    $param[$index]['num_doc'] = $payment->invoice->invoiceNumber();
                    $param[$index]['data_doc'] = $payment->invoice->invoice_date->format('d/m/Y');
                    $param[$index]['desc'] = 'S/DO FATTURA ' . strtoupper($payment->invoice->client->denomination) . '<br>Doc. orig. ' . $payment->invoice->invoiceNumber();
                    // $saldo -= $payment->amount;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $payment->amount ?? 0;
                    // $param[$index]['saldo'] = $saldo;
                }
            }
            $index++;
        }

        return $param;
    }

    private function createYearLedger($input): array
    {
        $param = [];
        $index = 0;
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture e note di credito con invoice_date nel periodo
        $invoices = $tenant
            ->invoices()
            ->with(['docType', 'invoice'])
            ->whereHas('docType', fn($q) => $q->whereIn('name', ['TD01', 'TD04', 'TD99']))
            ->when($input['client_id'], fn($q) => $q->where('client_id', $input['client_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('invoices.year', 'asc')
            ->orderBy('invoices.sectional_id', 'asc')
            ->orderBy('invoices.number', 'asc')
            ->get();

        foreach ($invoices as $invoice) {
            $amount = $invoice->is_total_with_vat ? $invoice->total : $invoice->no_vat_total;

            $param[$index]['order'] = \Carbon\Carbon::parse($invoice->invoice_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            $param[$index]['cliente']['nome'] = $invoice->client->denomination;
            $param[$index]['cliente']['pi'] = $invoice->client->vat_code;
            $param[$index]['cliente']['cf'] = $invoice->client->tax_code;
            $param[$index]['num_doc'] = $invoice->invoiceNumber();
            $param[$index]['data_doc'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');

            switch ($invoice->docType->name) {
                case 'TD01':
                    $param[$index]['desc'] = 'Ns. Fattura<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    break;
                case 'TD04':
                    $param[$index]['desc'] = 'N.C. su ' . $invoice->invoice?->invoiceNumber() . '<br>Doc. orig. ' . $invoice->invoiceNumber();
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                default:
                    break;
            }

            $index++;
        }

        // 2. Pagamenti con payment_date nel periodo, INDIPENDENTEMENTE dalla data della fattura collegata
        $payments = \App\Models\ActivePayments::query()
            ->where('company_id', $tenant->id)
            ->with('invoice.client')
            ->whereHas('invoice', function ($q) use ($input) {
                $q->when($input['client_id'], fn($qq) => $qq->where('client_id', $input['client_id']));
            })
            ->when($input['from_date'], fn($q) => $q->where('payment_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('payment_date', '<=', $input['to_date']))
            ->get();

        foreach ($payments as $payment) {
            $param[$index]['order'] = \Carbon\Carbon::parse($payment->payment_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y');
            $param[$index]['cliente']['nome'] = $payment->invoice->client->denomination;
            $param[$index]['cliente']['pi'] = $payment->invoice->client->vat_code;
            $param[$index]['cliente']['cf'] = $payment->invoice->client->tax_code;
            $param[$index]['num_doc'] = $payment->invoice->invoiceNumber();
            $param[$index]['data_doc'] = $payment->invoice->invoice_date->format('d/m/Y');
            $param[$index]['desc'] = 'S/DO FATTURA ' . strtoupper($payment->invoice->client->denomination) . '<br>Doc. orig. ' . $payment->invoice->invoiceNumber();
            $param[$index]['dare'] = 0;
            $param[$index]['avere'] = $payment->amount ?? 0;
            $index++;
        }

        return $param;
    }

    private function getPrecResidue($data)
    {
        $type = $data['type'] ?? 'accrual';
        $historicResidue = 0;

        if ($data['client_id']) {
            $client = Client::find($data['client_id']);
            $historicResidue = $client ? (float) $client->residue : 0;
        } else {
            $historicResidue = (float) Client::sum('residue');
        }

        if (!$data['from_date']) {
            return $historicResidue;
        }

        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture/quadrature (TD01, TD99) con invoice_date < from_date: dare pregresso
        $invoicesQuery = $tenant
            ->invoices()
            ->whereHas('docType', fn($q) => $q->whereIn('name', ['TD01', 'TD99']))
            ->where('invoices.invoice_date', '<', $data['from_date'])
            ->when($data['client_id'], fn($q) => $q->where('invoices.client_id', $data['client_id']));

        $totalDare = 0;
        foreach ((clone $invoicesQuery)->get() as $invoice) {
            $totalDare += $invoice->is_total_with_vat ? $invoice->total : $invoice->no_vat_total;
        }

        // 2. Note di credito (TD04) con invoice_date < from_date: avere pregresso
        $creditNotesQuery = $tenant
            ->invoices()
            ->whereHas('docType', fn($q) => $q->where('name', 'TD04'))
            ->where('invoices.invoice_date', '<', $data['from_date'])
            ->when($data['client_id'], fn($q) => $q->where('invoices.client_id', $data['client_id']));

        $totalAvereNote = 0;
        foreach ($creditNotesQuery->get() as $note) {
            $totalAvereNote += $note->is_total_with_vat ? $note->total : $note->no_vat_total;
        }

        // 3. Pagamenti: la logica CAMBIA in base al tipo di partitario
        if ($type === 'accrual') {
            // Competenza: contano TUTTI i pagamenti collegati alle fatture pregresse,
            // indipendentemente da payment_date (non compariranno mai come riga separata)
            $totalPayment = (float) (clone $invoicesQuery)->sum('total_payment');
        } else {
            // Esercizio: contano solo i pagamenti con payment_date < from_date, su QUALSIASI fattura,
            // perché i pagamenti dopo from_date compariranno come righe indipendenti
            $totalPayment = (float) \App\Models\ActivePayments::query()
                ->where('company_id', $tenant->id)
                ->whereHas('invoice', fn($q) => $q->when($data['client_id'], fn($qq) => $qq->where('client_id', $data['client_id'])))
                ->where('payment_date', '<', $data['from_date'])
                ->sum('amount');
        }

        $residue = $historicResidue + $totalDare - $totalAvereNote - $totalPayment;

        return (float) $residue;
    }

    private function closeOpen($data, $temp)
    {
        $param = [];
        $index = 0;
        $residue = $this->getPrecResidue($data);

        for ($i = 0; $i < count($temp); $i++) {
            $param[$index] = $temp[$i];
            $currentSaldo = $temp[$i]['saldo'];

            $currentYear = \Carbon\Carbon::createFromTimestampMs($temp[$i]['order'])->year;

            if ($i < count($temp) - 1) {
                $nextYear = \Carbon\Carbon::createFromTimestampMs($temp[$i + 1]['order'])->year;

                if ($currentYear !== $nextYear) {
                    $amountToClose = $currentSaldo;                                            // saldo reale accumulato fino a qui

                    $dareChiusura = $amountToClose < 0 ? abs($amountToClose) : 0;
                    $avereChiusura = $amountToClose > 0 ? $amountToClose : 0;

                    $param[++$index] = [
                        'auto' => true,
                        'order' => \Carbon\Carbon::create($currentYear, 12, 31, 23, 59, 59)->valueOf(),
                        'reg' => \Carbon\Carbon::create($currentYear, 12, 31)->format('d/m/Y'),
                        'cliente' => ['nome' => '', 'pi' => '', 'cf' => ''],
                        'num_doc' => '',
                        'data_doc' => '',
                        'desc' => 'SALDO CHIUSURA AL 31/12/' . $currentYear,
                        'dare' => $dareChiusura,
                        'avere' => $avereChiusura,
                        'saldo' => 0,
                    ];

                    $dareApertura = $amountToClose > 0 ? $amountToClose : 0;
                    $avereApertura = $amountToClose < 0 ? abs($amountToClose) : 0;

                    $param[++$index] = [
                        'auto' => true,
                        'order' => \Carbon\Carbon::create($nextYear, 1, 1)->startOfDay()->valueOf(),
                        'reg' => \Carbon\Carbon::create($nextYear, 1, 1)->format('d/m/Y'),
                        'cliente' => ['nome' => '', 'pi' => '', 'cf' => ''],
                        'num_doc' => '',
                        'data_doc' => '',
                        'desc' => 'SALDO APERTURA AL 01/01/' . $nextYear,
                        'dare' => $dareApertura,
                        'avere' => $avereApertura,
                        'saldo' => 0,
                    ];
                }
            }

            $index++;
        }

        usort($param, fn($a, $b) => $a['order'] <=> $b['order']);

        $currentSaldo = $data['prec_residue'] ? $residue : 0;
        foreach ($param as &$entry) {
            $currentSaldo += $entry['dare'] - $entry['avere'];
            $entry['saldo'] = $currentSaldo;
        }

        return $param;
    }

    private function generatePdfOutput($data, $residue, $param, $tenant)
    {
        $clientName = $data['client_id'] ? Client::find($data['client_id'])->denomination : '';
        $type = $data['type'] === 'accrual' ? 'Competenza' : 'Esercizio';
        $span = '_';
        $from_formatted = $data['from_date'] ? (new DateTime($data['from_date']))->format('d-m-Y') : null;
        $to_formatted = $data['to_date'] ? (new DateTime($data['to_date']))->format('d-m-Y') : null;
        if ($data['from_date'] && $data['to_date']) $span .= "Dal {$from_formatted} al {$to_formatted}";
        else if ($data['from_date']) $span .= "Dal {$from_formatted}";
        else if ($data['to_date']) $span .= "Fino al {$to_formatted}";
        return response()->streamDownload(function () use ($data, $residue, $param, $tenant) {
            echo Pdf::loadHTML(
                Blade::render('pdf.ledger', [
                    'company' => $tenant,
                    'filters' => $data,
                    'residue' => $residue,
                    'data' => $param,
                ])
            )
                ->setPaper('A4', 'portrait')
                ->stream();
        }, "Partitario_{$clientName}_{$type}{$span}.pdf");
    }

    // Metodo per generare output Excel
    protected function generateExcelOutput($data, $residue, $param, $tenant)
    {
        // Prepara i dati per Excel
        $excelData = [];

        // Header
        $excelData[] = [
            'Data Reg.',
            'Cliente',
            'P.IVA',
            'Cod.Fiscale',
            'Num. Doc.',
            'Data Doc.',
            'Descrizione',
            'Dare',
            'Avere',
            'Saldo'
        ];

        // Se c'è un residuo precedente, aggiungilo come prima riga
        if ($data['prec_residue']) {
            $excelData[] = [
                '',
                '',
                '',
                '',
                '',
                '',
                'Residuo precedente',
                '',
                '',
                $residue
            ];
        }

        // Dati del partitario
        foreach ($param as $row) {
            $excelData[] = [
                $row['reg'],
                $row['cliente']['nome'] ?? '',
                $row['cliente']['pi'] ?? '',
                $row['cliente']['cf'] ?? '',
                $row['num_doc'] ?? '',
                $row['data_doc'] ?? '',
                strip_tags(str_replace('<br>', ' - ', $row['desc'] ?? '')), // Rimuove HTML e sostituisce <br>
                $row['dare'] ?? 0,
                $row['avere'] ?? 0,
                $row['saldo'] ?? 0
            ];
        }

        $clientName = $data['client_id'] ? Client::find($data['client_id'])->denomination : '';
        $type = $data['type'] === 'accrual' ? 'Competenza' : 'Esercizio';
        $span = '_';
        $from_formatted = $data['from_date'] ? (new DateTime($data['from_date']))->format('d-m-Y') : null;
        $to_formatted = $data['to_date'] ? (new DateTime($data['to_date']))->format('d-m-Y') : null;
        if ($data['from_date'] && $data['to_date']) $span .= "Dal {$from_formatted} al {$to_formatted}";
        else if ($data['from_date']) $span .= "Dal {$from_formatted}";
        else if ($data['to_date']) $span .= "Fino al {$to_formatted}";

        return response()->streamDownload(function () use ($excelData) {
            // Crea un nuovo spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Imposta il titolo del foglio
            $sheet->setTitle('Partitario');

            // Scrive i dati
            $sheet->fromArray($excelData, null, 'A1');

            // Formattazione header
            $headerStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                    ]
                ]
            ];
            $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

            // Formattazione colonne numeriche (Dare, Avere, Saldo)
            $numberStyle = [
                'numberFormat' => ['formatCode' => '#,##0.00']
            ];
            $sheet->getStyle('H:J')->applyFromArray($numberStyle);

            // Auto-dimensiona le colonne
            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Crea il writer e salva
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, "Partitario_{$clientName}_{$type}{$span}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Partitario_{$clientName}_{$type}{$span}.xlsx"'
        ]);
    }
}
