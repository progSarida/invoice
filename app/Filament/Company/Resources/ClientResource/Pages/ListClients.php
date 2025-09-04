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
use App\Models\Client;
use App\Models\ManageType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
                                ->placeholder('Tutti')
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
                                ->default(false),
                            Placeholder::make('')
                                ->content('')
                                ->columnSpan(9),
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
                    // dd($data);
                    $clientId = $data['client_id'] ?? null;
                    $fromDate = $data['from_date'] ?? null;
                    $toDate = $data['to_date'] ?? null;
                    $precResidue = $data['prec_residue'];
                    $outputFormat = $data['output_format'] ?? 'pdf';

                    $invoices = \Filament\Facades\Filament::getTenant()
                        ->invoices()
                        ->with([
                            'activePayments' => function ($query) use ($fromDate, $toDate) {
                                $query->when($fromDate, fn($q) => $q->where('payment_date', '>=', $fromDate))
                                    ->when($toDate, fn($q) => $q->where('payment_date', '<=', $toDate));
                                    // ->orderBy('updated_at');
                            },
                            'docType',
                            'invoice',
                        ])
                        ->when($clientId, fn($q) => $q->where('client_id', $clientId))
                        ->when($fromDate, fn($q) => $q->where('invoice_date', '>=', $fromDate))
                        ->when($toDate, fn($q) => $q->where('invoice_date', '<=', $toDate))
                        ->where('flow', 'out')
                        // ->orderBy('updated_at')
                        ->get();
                    // dd($invoices);

                    $param = [];
                    $residue = $this->getPrecResidue($data);                                                            // residuo precedente
                    $saldo = $precResidue ? $residue : 0;                                                               // saldo iniziale
                    $index = 0;
                    foreach($invoices as $key => $invoice) {
                        $param[$index]['order'] = \Carbon\Carbon::parse($invoice->created_at)->valueOf();
                        $param[$index]['reg'] = \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y');
                        $param[$index]['cliente']['nome'] = $invoice->client->denomination;
                        $param[$index]['cliente']['pi'] = $invoice->client->vat_code;
                        $param[$index]['cliente']['cf'] = $invoice->client->tax_code;
                        $param[$index]['num_doc'] = $invoice->invoiceNumber();
                        $param[$index]['data_doc'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
                        $param[$index]['desc'] = $invoice->description;
                        $amount = $invoice->client?->type?->value == 'public' ? $invoice->no_vat_total : $invoice->total;
                        switch($invoice->docType->name) {
                            case 'TD02':                                                                                // acconti/anticipi su fattura
                                // $saldo -= $amount;
                                $param[$index]['desc'] = 'Acconto<br>Doc. orig. ' . $invoice->invoiceNumber();
                                $param[$index]['dare'] = 0;
                                $param[$index]['avere'] = $amount;
                                // $param[$index]['saldo'] = $saldo;
                                break;
                            case 'TD03':                                                                                // acconti/anticipi su parcella
                                // $saldo -= $amount;
                                $param[$index]['desc'] = 'Acconto su parcella';
                                $param[$index]['dare'] = 0;
                                $param[$index]['avere'] = $amount;
                                // $param[$index]['saldo'] = $saldo;
                                break;
                            case 'TD04':                                                                                // nota di credito
                                // $saldo -= $amount;
                                // dd($invoice);
                                $param[$index]['desc'] = 'N.C. su ' . $invoice->invoice->invoiceNumber() . '<br>Doc. orig. ' . $invoice->invoiceNumber();
                                $param[$index]['dare'] = 0;
                                $param[$index]['avere'] = $amount;
                                // $param[$index]['saldo'] = $saldo;
                                break;
                            case 'TD01':                                                                                // fattura
                                // $saldo += $amount;
                                $param[$index]['desc'] = 'Ns. Fattura<br>Doc. orig. ' . $invoice->invoiceNumber();
                                $param[$index]['dare'] = $amount;
                                $param[$index]['avere'] = 0;
                                // $param[$index]['saldo'] = $saldo;
                                break;
                            default:                                                                                    // tutta gli altri tipi di documento
                                // $saldo += $amount;
                                $param[$index]['desc'] = $invoice->description;
                                $param[$index]['dare'] = $amount;
                                $param[$index]['avere'] = 0;
                                // $param[$index]['saldo'] = $saldo;
                                break;
                        }
                        if($invoice->activePayments) {
                            foreach($invoice->activePayments as $payment){
                                $index++;
                                $param[$index]['order'] = \Carbon\Carbon::parse($payment->created_at)->valueOf();
                                $param[$index]['reg'] = \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y');
                                $param[$index]['cliente']['nome'] = $payment->invoice->client->denomination;
                                $param[$index]['cliente']['pi'] = $payment->invoice->client->vat_code;
                                $param[$index]['cliente']['cf'] = $payment->invoice->client->tax_code;
                                $param[$index]['num_doc'] = $payment->invoice->invoiceNumber();
                                $param[$index]['data_doc'] = $payment->invoice->invoice_date->format('d/m/Y');
                                $param[$index]['desc'] = 'S/DO FATTURA ' . strtoupper($payment->invoice->client->denomination) . '<br>Doc. orig. ' . $payment->invoice->invoiceNumber();
                                // $saldo -= $payment->amount;
                                $param[$index]['dare'] = 0;
                                $param[$index]['avere'] = $payment->amount;
                                // $param[$index]['saldo'] = $saldo;
                            }
                        }
                        $index++;
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
                    }, 'Partitario.pdf');
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

    private function getPrecResidue($data)																		// calcolo residuo precedente
    {
        $residue = 0;
        $historicResidue = 0;

        if ($data['client_id']) {																				// è stato selezionato un cliente
            $client = Client::find($data['client_id']);
            $historicResidue = $client ? $client->residue : 0;													// residuo storico del cliente
        } else {																								// nessun cliente selezionato
            $historicResidue = Client::sum('residue');															// residuo storico totale
        }

        $invoices = \Filament\Facades\Filament::getTenant()													    // fatture su cui fare il calcolo
                ->invoices()
                ->where('flow', 'out')                                                                          // solo fatture nuove
                ->whereHas('docType', fn($q) => $q->where('name', 'TD01'))                                      // recupero solo le fatture
                ->when($data['from_date'], fn($q) => $q->where('invoice_date', '<', $data['from_date']))
                ->when(!$data['from_date'], fn($q) => $q->whereRaw('1 = 0'))
                ->when($data['client_id'], fn($q) => $q->where('client_id', $data['client_id']));

        $residue = $historicResidue 																			// al residuo storico
            + $invoices->sum('total')                                                                           // sommo i totali delle fatture
            - $invoices->sum('total_notes')                                                                     // sottraggo i totali delle note di credito delle fatture
            - $invoices->sum('total_payment');                                                                  // sottraggo i totali dei pagamenti delle fatture

        // dd($residue);

        $totals = $invoices->selectRaw('
                SUM(CASE
                    WHEN clients.type = \'public\'
                    THEN invoices.no_vat_total
                    ELSE invoices.total
                END) as total_sum,
                SUM(total_notes) as notes_sum,
                SUM(total_payment) as payment_sum
            ')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->first();

        $residue = $historicResidue                                                                             // al residuo storico
            + ($totals->total_sum ?? 0)                                                                         // sommo i totali delle fatture (con logica condizionale)
            - ($totals->notes_sum ?? 0)                                                                         // sottraggo i totali delle note di credito
            - ($totals->payment_sum ?? 0);                                                                      // sottraggo i totali dei pagamenti

        return (float) $residue;

        return $residue;
    }

    private function closeOpen($data, $temp)
    {
        // if (empty($temp) || empty($data['from_date']) || empty($data['to_date'])) {                             // non ci sono voci o intervallo di date, restituisci inalterato
        //     return $temp;
        // }

        $param = [];                                                                                            // output
        $index = 0;                                                                                             // indice param
        $first = true;                                                                                          // flag primo storno (residuo precedente)
        $annual = 0;                                                                                            // saldo da stornare per gli anni successivi
        $residue = $this->getPrecResidue($data);                                                                // residuo precedente
        $currentSaldo = $residue;                                                                               // inizializzo il saldo con il residuo

        for ($i = 0; $i < count($temp); $i++) {                                                                 // ciclo sugli elementi di $temp
            $param[$index] = $temp[$i];                                                                         // aggiungo l'elemento corrente di $temp
            $currentSaldo = $temp[$i]['saldo'];                                                                 // aggiorno il saldo corrente

            $currentDate = \Carbon\Carbon::createFromFormat('d/m/Y', $temp[$i]['reg']);
            $currentYear = $currentDate->year;

            if ($i < count($temp) - 1) {                                                                        // controllo se l'elemento successivo esiste
                $nextDate = \Carbon\Carbon::createFromFormat('d/m/Y', $temp[$i + 1]['reg']);
                $nextYear = $nextDate->year;

                if ($currentYear !== $nextYear) {                                                               // controllo se l'elemento successivo appartiene a un anno diverso
                    // $amountToClose = $first ? $residue : $annual;                                               // imposto il valore da stornare
                    $amountToClose = $first ? ($data['prec_residue'] ? $residue : 0) : $annual;                 // imposto il valore da stornare
                    $annual = $currentSaldo;                                                                    // salvo il saldo per l'apertura successiva

                    $param[++$index] = [                                                                        // aggiungo la riga di chiusura (31/12)
                        'auto' => true,
                        'order' => \Carbon\Carbon::create($currentYear, 12, 31, 23, 59, 59)->valueOf(),
                        'reg' => \Carbon\Carbon::create($currentYear, 12, 31)->format('d/m/Y'),
                        'cliente' => ['nome' => '', 'pi' => '', 'cf' => ''],
                        'num_doc' => '',
                        'data_doc' => '',
                        'desc' => 'SALDO CHIUSURA AL 31/12/' . $currentYear,
                        'dare' => 0,
                        'avere' => $amountToClose,
                        'saldo' => 0,
                    ];

                    $param[++$index] = [                                                                        // aggiungo la riga di apertura (01/01 dell'anno successivo)
                        'auto' => true,
                        'order' => \Carbon\Carbon::create($nextYear, 1, 1)->startOfDay()->valueOf(),
                        'reg' => \Carbon\Carbon::create($nextYear, 1, 1)->format('d/m/Y'),
                        'cliente' => ['nome' => '', 'pi' => '', 'cf' => ''],
                        'num_doc' => '',
                        'data_doc' => '',
                        'desc' => 'SALDO APERTURA AL 01/01/' . $nextYear,
                        'dare' => $amountToClose,
                        'avere' => 0,
                        'saldo' => 0,
                    ];

                    $first = false;                                                                             // dopo il primo storno disabilito il flag
                }
            }

            $index++;
        }

        // Aggiungo chiusura per l'ultimo anno se necessario
        // $lastDate = \Carbon\Carbon::createFromFormat('d/m/Y', $temp[count($temp) - 1]['reg']);
        // if ($lastDate === false) {
        //     $lastYear = \Carbon\Carbon::parse($data['to_date'])->year;
        // } else {
        //     $lastYear = $lastDate->year;
        // }
        // $toDateYear = \Carbon\Carbon::parse($data['to_date'])->year;

        // if ($lastYear <= $toDateYear && ($currentSaldo != 0 || ($first && $residue != 0))) {
        //     $amountToClose = $first ? $residue : $currentSaldo;

        //     // Aggiungi la riga di chiusura per l'ultimo anno
        //     $param[$index] = [
        //         'auto' => true,
        //         'order' => \Carbon\Carbon::create($lastYear, 12, 31, 23, 59, 59)->valueOf(),
        //         'reg' => \Carbon\Carbon::create($lastYear, 12, 31)->format('d/m/Y'),
        //         'cliente' => ['nome' => '', 'pi' => '', 'cf' => ''],
        //         'num_doc' => '',
        //         'data_doc' => '',
        //         'desc' => 'SALDO CHIUSURA AL 31/12/' . $lastYear,
        //         'dare' => $amountToClose < 0 ? abs($amountToClose) : 0,
        //         'avere' => $amountToClose > 0 ? abs($amountToClose) : 0,
        //         'saldo' => 0,
        //     ];
        // }

        usort($param, function ($a, $b) {                                                                       // ordino gli elementi per 'order'
            return $a['order'] <=> $b['order'];
        });

        $currentSaldo = $data['prec_residue'] ? $residue : 0;                                                   // ricalcolo il saldo per tutte le voci
        foreach ($param as &$entry) {
            $currentSaldo += $entry['dare'] - $entry['avere'];
            $entry['saldo'] = $currentSaldo;
        }

        return $param;
    }

    private function generatePdfOutput($data, $residue, $param, $tenant)
    {
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
        }, 'Partitario.pdf');
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
                $residue < 0 ? abs($residue) : 0,
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
        }, 'Partitario.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Partitario.xlsx"'
        ]);
    }
}
