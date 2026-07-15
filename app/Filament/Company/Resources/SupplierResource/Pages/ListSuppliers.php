<?php

namespace App\Filament\Company\Resources\SupplierResource\Pages;

use App\Filament\Company\Resources\SupplierResource;
use App\Models\Company;
use App\Models\DocType;
use App\Models\PassiveInvoice;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('ledger')
                ->icon('tabler-report-search')
                ->label('Partitario')
                // ->visible(false)
                ->tooltip('Stampa partitario fornitori')
                // ->color('primary')
                ->color(Color::rgb('rgb(255, 0, 0)'))
                ->modalWidth('6xl')
                ->modalHeading('Stampa partitario')
                ->form([
                    \Filament\Forms\Components\Grid::make(12)
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Fornitore')
                                ->placeholder('Tutti')
                                ->columnSpan(6)
                                ->getSearchResultsUsing(function (string $search) {
                                    // Rimuovi spazi multipli e trim
                                    $search = trim(preg_replace('/\s+/', ' ', $search));

                                    $query = Supplier::query();

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
                                            $denomination = $record->denomination ?? 'N/A';
                                            // $label = strtoupper("{$subtype}") . " - $denomination";
                                            $label = $denomination;

                                            return [$record->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function (?int $value) {
                                    if (!$value) { return null; }
                                    $record = Supplier::find($value);
                                    if (!$record) { return null; }
                                    // return strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination";
                                    return $record->denomination;
                                })
                                ->getOptionLabelFromRecordUsing(
                                    // fn (Model $record) => strtoupper("{$record->subtype->getLabel()}") . " - $record->denomination"
                                    fn (Model $record) => $record->denomination
                                )
                                // ->options(function () {
                                //     $docs = \Filament\Facades\Filament::getTenant()->suppliers()->select('suppliers.id', 'suppliers.denomination')->get();
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
                                ->live()
                                ->default('accrual')
                                ->inline()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $names = PassiveInvoice::distinct()->orderBy('doc_type')->pluck('doc_type')->toArray();
                                    $filter = ['TD17', 'TD18','TD19'];                                       // INSERIRE DOCUMENTI NON PREVISTI NEL PARTITARIO
                                    if ($state === 'accrual') { $filter[] = 'TD04'; }
                                    $names = array_values(array_diff($names, $filter));
                                    
                                    $defaultDocTypes = DocType::whereIn('name', $names)->pluck('name')->toArray();
                                        
                                    $set('docTypes', $defaultDocTypes);
                                })
                                ->columnSpan(5),
                            Checkbox::make('prec_residue')
                                ->label('Con residuo precedente')
                                ->columnSpan(4)
                                ->default(true),
                            Section::make('Documenti da inserire')
                                ->columns(24)
                                ->collapsed()
                                ->schema([
                                    CheckboxList::make('docTypes')
                                        ->label('')
                                        ->required()
                                        // ->options(DocType::pluck('description', 'name')->toArray())
                                        ->options(DocType::pluck('description', 'name')->toArray())
                                        ->columns(2)
                                        ->dehydrated(true)
                                        ->columnSpan(['sm' => 'full', 'md' => 24])
                                        ->default(function($record, $get) {
                                            $names = PassiveInvoice::distinct()->orderBy('doc_type')->pluck('doc_type')->toArray();
                                            $filter = ['TD04', 'TD17', 'TD18', 'TD19'];                 // INSERIRE DOCUMENTI NON PREVISTI NEL PARTITARIO
                                            $names = array_values(array_diff($names, $filter));
                                            return DocType::whereIn('name', $names)->pluck('name')->toArray();
                                        })
                                        ->gridDirection('row'),
                                ])
                                ->columnSpanFull(),
                            Placeholder::make('last')
                                ->label('')
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
                    return $this->printLedger($data);
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }

    private function printLedger(array $data)
    {
        // dd($data);
        $input = [];
        $input['supplier_id'] = $supplierId = $data['supplier_id'] ?? null;
        $input['from_date'] = $fromDate = $data['from_date'] ?? null;
        $input['to_date'] = $toDate = $data['to_date'] ?? null;
        $input['type'] = $type = $data['type'];
        $input['prec_residue'] = $precResidue = $data['prec_residue'];
        $input['docTypes'] = $docTypes = $data['docTypes'];
        $input['output_format'] = $outputFormat = $data['output_format'] ?? 'pdf';

        // $residue = $this->getPrecResidue($input);                                                            // residuo precedente   
        $residue = 0;                            
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
            return $this->generateExcelOutput($data, $residue, $param);
        } else {
            return $this->generatePdfOutput($data, $residue, $param, $tenant);
        }
    }
    
    private function createAccrualLedgerOld(array $input): array
    {
        $param = [];
        $index = 0;
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture e note di credito con invoice_date nel periodo
        $passiveInvoices = $tenant
            ->passiveInvoices()
            ->with('docType')
            ->whereNull('parent_id')
            ->whereIn('doc_type', ['0000', 'TD01', 'TD02', 'TD03', 'TD05', 'TD06', 'TD24'])
            ->when($input['supplier_id'], fn($q) => $q->where('supplier_id', $input['supplier_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('passive_invoices.invoice_date', 'asc')
            ->get();

        foreach ($passiveInvoices as $invoice) {
            $amount = $invoice->total;

            $param[$index]['order'] = \Carbon\Carbon::parse($invoice->invoice_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            $param[$index]['fornitore']['nome'] = $invoice->supplier?->denomination;
            $param[$index]['fornitore']['pi'] = $invoice->supplier?->vat_code;
            $param[$index]['fornitore']['cf'] = $invoice->supplier?->tax_code;
            $param[$index]['num_doc'] = $invoice->number;
            $param[$index]['data_doc'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');

            switch($invoice->docType->name) {
                case '0000':                                                                                // fattura
                    $param[$index]['desc'] = 'FT.ACQ.RSM/VATIC./ NoUE ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD01':                                                                                // fattura
                    $param[$index]['desc'] = 'Fattura<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD02':                                                                                // acconti/anticipi su fattura
                    $param[$index]['desc'] = 'Acconto su fattura<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD03':                                                                                // acconti/anticipi su parcella
                    // $saldo -= $amount;
                    $param[$index]['desc'] = 'Acconto su parcella<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                // case 'TD04':                                                                                // nota di credito
                //     $param[$index]['desc'] = 'N.C. su ' . $invoice->parent?->number . '<br>Doc. orig. ' . $invoice->number;
                //     $param[$index]['dare'] = 0;
                //     $param[$index]['avere'] = $amount ?? 0;
                //     break;
                case 'TD05':                                                                                // nota di credito
                    $param[$index]['desc'] = 'N.D. su ' . $invoice->parent?->number . '<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD06':                                                                                // nota di credito
                    $param[$index]['desc'] = 'N.C. su ' . $invoice->parent?->number . '<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD24':                                                                                // nota di credito
                    $param[$index]['desc'] = 'Fattura differita<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                default:                                                                                    // tutta gli altri tipi di documento
                    $param[$index]['desc'] = $invoice->description;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
            }

            if($invoice->creditNotes) {
                foreach($invoice->creditNotes as $note){
                    $amount = $note->total;
                    $index++;
                    $param[$index]['order'] = \Carbon\Carbon::parse($note->invoice_date)->valueOf();
                    $param[$index]['reg'] = \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y');
                    $param[$index]['fornitore']['nome'] = $note->supplier?->denomination;
                    $param[$index]['fornitore']['pi'] = $note->supplier?->vat_code;
                    $param[$index]['fornitore']['cf'] = $note->supplier?->tax_code;
                    $param[$index]['num_doc'] = $note->number;
                    $param[$index]['data_doc'] = \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y');
                    $param[$index]['desc'] = 'N.C. su ' . $note->invoice?->number . '<br>Doc. orig. ' . $note->number;
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                }
            }

            if($invoice->passivePayments) {
                foreach($invoice->passivePayments as $payment){
                    $index++;
                    // $param[$index]['order'] = \Carbon\Carbon::parse($payment->created_at)->valueOf();
                    $param[$index]['order'] = \Carbon\Carbon::parse($payment->payment_date)->valueOf();
                    $param[$index]['reg'] = \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y');
                    // $param[$index]['reg'] = \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y');
                    $param[$index]['fornitore']['nome'] = $payment->passiveInvoice->supplier?->denomination;
                    $param[$index]['fornitore']['pi'] = $payment->passiveInvoice->supplier?->vat_code;
                    $param[$index]['fornitore']['cf'] = $payment->passiveInvoice->supplier?->tax_code;
                    $param[$index]['num_doc'] = $payment->passiveInvoice->number;
                    $param[$index]['data_doc'] = $payment->passiveInvoice->invoice_date->format('d/m/Y');
                    $param[$index]['desc'] = 'ESTRATTO CONTO | FAT. ' . $payment->passiveInvoice->number;
                    // $saldo -= $payment->amount;
                    $param[$index]['dare'] = $payment->amount ?? 0;
                    $param[$index]['avere'] = 0;
                    // $param[$index]['saldo'] = $saldo;
                }
            }
            $index++;
        }

        return $param;
    }

    private function createAccrualLedger(array $input): array
    {
        $param = [];
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture (tipi selezionati dall'utente) con invoice_date nel periodo
        $passiveInvoices = $tenant
            ->passiveInvoices()
            ->with(['docType', 'supplier', 'creditNotes', 'debitNotes', 'passivePayments'])
            ->whereNull('parent_id')
            ->when($input['docTypes'], fn($q) => $q->whereIn('doc_type', $input['docTypes']))
            ->when($input['supplier_id'], fn($q) => $q->where('supplier_id', $input['supplier_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('passive_invoices.invoice_date', 'asc')
            ->get();

        foreach ($passiveInvoices as $invoice) {
            $amount = $invoice->total;
            $addVat = false;

            $row = [
                'order' => \Carbon\Carbon::parse($invoice->invoice_date)->valueOf(),
                'reg' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                'fornitore' => [
                    'nome' => $invoice->supplier?->denomination,
                    'pi' => $invoice->supplier?->vat_code,
                    'cf' => $invoice->supplier?->tax_code,
                ],
                'num_doc' => $invoice->number,
                'data_doc' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                'desc' => '',
                'dare' => 0,
                'avere' => 0,
            ];

            switch ($invoice->docType->name) {
                case '0000':
                case 'TD17':
                    $row['desc'] = $invoice->supplier->country == 'US' ? 'FT. ACQ. RSM/VATIC./NoUE' : 'FT. ACQUISTO INTRA';
                    $row['avere'] = (float) $amount * 1.22 ?? 0;
                    $addVat = true;
                    break;
                case 'TD01':
                case 'TD02':
                case 'TD03':
                case 'TD06':
                case 'TD19':
                case 'TD24':
                    $row['desc'] = 'FT. ACQUISTO';
                    $row['avere'] = $amount ?? 0;
                    break;
                case 'TD04':
                    $row['desc'] = 'FT. ACQUISTO N. ACCREDITO';
                    $row['avere'] = $amount ?? 0;
                    break;
                case 'TD05':
                    $row['desc'] = 'FT. ACQUISTO N. ADDEBITO';
                    $row['avere'] = $amount ?? 0;
                    break;
                case 'TD18':
                    $row['desc'] = 'FT. ACQUISTO INTRA';
                    $row['avere'] = $amount ?? 0;
                    break;
            }

            $param[] = $row;

        if($addVat){
            $param[] = [
                'order' => \Carbon\Carbon::parse($invoice->invoice_date)->valueOf(),
                'reg' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                'fornitore' => [
                    'nome' => $invoice->supplier?->denomination,
                    'pi' => $invoice->supplier?->vat_code,
                    'cf' => $invoice->supplier?->tax_code,
                ],
                'num_doc' => $invoice->number,
                'data_doc' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                'desc' => $invoice->supplier->country == 'US' 
                            ? 'FT. ACQ. RSM/VATIC./NoUE|Acquisto da non resid. art. 17 <b>#</b>' 
                            : 'FT. ACQUISTO INTRA|Intra Acquisti intracomunitari <b>#</b>',
                'dare' => (float) $amount * 0.22 ?? 0,
                'avere' => 0,
            ];
        }

            // Note di credito ricevute: riducono il debito -> dare
            if ($invoice->creditNotes) {
                foreach ($invoice->creditNotes as $note) {
                    $param[] = [
                        'order' => \Carbon\Carbon::parse($note->invoice_date)->valueOf(),
                        'reg' => \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y'),
                        'fornitore' => [
                            'nome' => $note->supplier?->denomination,
                            'pi' => $note->supplier?->vat_code,
                            'cf' => $note->supplier?->tax_code,
                        ],
                        'num_doc' => $note->number,
                        'data_doc' => \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y'),
                        'desc' => 'FT. ACQUISTO N. ACCREDITO',
                        'dare' => $note->total ?? 0,
                        'avere' => 0,
                    ];
                }
            }

            if ($invoice->debitNotes) {
                foreach ($invoice->debitNotes as $note) {
                    $param[] = [
                        'order' => \Carbon\Carbon::parse($note->invoice_date)->valueOf(),
                        'reg' => \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y'),
                        'fornitore' => [
                            'nome' => $note->supplier?->denomination,
                            'pi' => $note->supplier?->vat_code,
                            'cf' => $note->supplier?->tax_code,
                        ],
                        'num_doc' => $note->number,
                        'data_doc' => \Carbon\Carbon::parse($note->invoice_date)->format('d/m/Y'),
                        'desc' => 'FT. ACQUISTO N. ADDEBITO',
                        'dare' => 0,
                        'avere' => $note->total ?? 0,
                    ];
                }
            }

            // Pagamenti collegati, INDIPENDENTEMENTE da payment_date (competenza)
            if ($invoice->passivePayments) {
                foreach ($invoice->passivePayments as $payment) {
                    $param[] = [
                        'order' => \Carbon\Carbon::parse($payment->payment_date)->valueOf(),
                        'reg' => \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y'),
                        'fornitore' => [
                            'nome' => $payment->passiveInvoice->supplier?->denomination,
                            'pi' => $payment->passiveInvoice->supplier?->vat_code,
                            'cf' => $payment->passiveInvoice->supplier?->tax_code,
                        ],
                        'num_doc' => $payment->passiveInvoice->number,
                        'data_doc' => $payment->passiveInvoice->invoice_date->format('d/m/Y'),
                        'desc' => 'PAGAMENTO | FAT. ' . $payment->passiveInvoice->number . ' ' . \Carbon\Carbon::parse($payment->passiveInvoice->invoice_date)->format('d/m/Y'),
                        'dare' => $payment->amount ?? 0,
                        'avere' => 0,
                    ];
                }
            }
        }

        return $param;
    }

    private function createYearLedgerOld(array $input): array
    {
        $param = [];
        $index = 0;
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture e note di credito con invoice_date nel periodo
        $passiveInvoices = $tenant
            ->invoices()
            ->with(['docType', 'invoice'])
            ->whereIn('doc_type', ['0000', 'TD01', 'TD02', 'TD03', 'TD04', 'TD06', 'TD24'])
            ->when($input['supplier_id'], fn($q) => $q->where('supplier_id', $input['supplier_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('invoices.year', 'asc')
            ->orderBy('invoices.sectional_id', 'asc')
            ->orderBy('invoices.number', 'asc')
            ->get();

        foreach ($passiveInvoices as $invoice) {
            $amount = $invoice->total;

            $param[$index]['order'] = \Carbon\Carbon::parse($invoice->invoice_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');
            $param[$index]['fornitore']['nome'] = $invoice->supplier?->denomination;
            $param[$index]['fornitore']['pi'] = $invoice->supplier?->vat_code;
            $param[$index]['fornitore']['cf'] = $invoice->supplier?->tax_code;
            $param[$index]['num_doc'] = $invoice->number;
            $param[$index]['data_doc'] = \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y');

            switch($invoice->docType->name) {
                case '0000':                                                                                // fattura
                    $param[$index]['desc'] = 'FT.ACQ.RSM/VATIC./ NoUE ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD01':                                                                                // fattura
                    $param[$index]['desc'] = 'Fattura<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD02':                                                                                // acconti/anticipi su fattura
                    $param[$index]['desc'] = 'Acconto su fattura<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD03':                                                                                // acconti/anticipi su parcella
                    // $saldo -= $amount;
                    $param[$index]['desc'] = 'Acconto su parcella<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD04':                                                                                // nota di credito
                    $param[$index]['desc'] = 'N.C. su ' . $invoice->parent?->number . '<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = $amount ?? 0;
                    $param[$index]['avere'] = 0;
                    break;
                case 'TD05':                                                                                // nota di credito
                    $param[$index]['desc'] = 'N.D. su ' . $invoice->parent?->number . '<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD06':                                                                                // nota di credito
                    $param[$index]['desc'] = 'N.C. su ' . $invoice->parent?->number . '<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                case 'TD24':                                                                                // nota di credito
                    $param[$index]['desc'] = 'Fattura differita<br>Doc. orig. ' . $invoice->number;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
                default:                                                                                    // tutta gli altri tipi di documento
                    $param[$index]['desc'] = $invoice->description;
                    $param[$index]['dare'] = 0;
                    $param[$index]['avere'] = $amount ?? 0;
                    break;
            }

            $index++;
        }

        // 2. Pagamenti con payment_date nel periodo, INDIPENDENTEMENTE dalla data della fattura collegata
        $passivePayments = \App\Models\PassivePayment::query()
            ->where('company_id', $tenant->id)
            ->with('passiveInvoice.supplier')
            ->whereHas('passiveInvoice', function ($q) use ($input) {
                $q->when($input['supplier_id'], fn($qq) => $qq->where('supplier_id', $input['supplier_id']));
            })
            ->when($input['from_date'], fn($q) => $q->where('payment_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('payment_date', '<=', $input['to_date']))
            ->get();

        foreach ($passivePayments as $payment) {
            $param[$index]['order'] = \Carbon\Carbon::parse($payment->payment_date)->valueOf();
            $param[$index]['reg'] = \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y');
            $param[$index]['cliente']['nome'] = $payment->passiveInvoice->supplier->denomination;
            $param[$index]['cliente']['pi'] = $payment->passiveInvoice->supplier->vat_code;
            $param[$index]['cliente']['cf'] = $payment->passiveInvoice->supplier->tax_code;
            $param[$index]['num_doc'] = $payment->passiveInvoice->number;
            $param[$index]['data_doc'] = $payment->passiveInvoice->invoice_date->format('d/m/Y');
            $param[$index]['desc'] = 'ESTRATTO CONTO | FAT. ' . $payment->passiveInvoice->number;
            $param[$index]['dare'] = $payment->amount ?? 0;
            $param[$index]['avere'] = 0;
            $index++;
        }

        return $param;
    }

    private function createYearLedger(array $input): array
    {
        $param = [];
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture e note di credito (tipi selezionati) con invoice_date nel periodo
        $passiveInvoices = $tenant
            ->passiveInvoices()                                        // <-- corretto: passiveInvoices(), non invoices()
            ->with(['docType', 'supplier', 'parent'])
            ->when($input['docTypes'], fn($q) => $q->whereIn('doc_type', $input['docTypes']))
            ->when($input['supplier_id'], fn($q) => $q->where('supplier_id', $input['supplier_id']))
            ->when($input['from_date'], fn($q) => $q->where('invoice_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('invoice_date', '<=', $input['to_date']))
            ->orderBy('passive_invoices.invoice_date', 'asc')
            ->get();

        foreach ($passiveInvoices as $invoice) {
            $amount = $invoice->total;
            $addVat = false;

            $row = [
                'order' => \Carbon\Carbon::parse($invoice->invoice_date)->valueOf(),
                'reg' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                'fornitore' => [
                    'nome' => $invoice->supplier?->denomination,
                    'pi' => $invoice->supplier?->vat_code,
                    'cf' => $invoice->supplier?->tax_code,
                ],
                'num_doc' => $invoice->number,
                'data_doc' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                'desc' => '',
                'dare' => 0,
                'avere' => 0,
            ];

            switch ($invoice->docType->name) {
                case '0000':
                // case 'TD17':
                    $row['desc'] = $invoice->supplier->country == 'US' ? 'FT. ACQ. RSM/VATIC./NoUE' : 'FT. ACQUISTO INTRA';
                    $row['avere'] = (float) $amount * 1.22 ?? 0;
                    $addVat = true;
                    break;
                case 'TD01':
                case 'TD02':
                case 'TD03':
                case 'TD06':
                // case 'TD19':
                case 'TD24':
                    $row['desc'] = 'FT. ACQUISTO';
                    $row['avere'] = $amount ?? 0;
                    break;
                case 'TD04':
                    $row['desc'] = 'FT. ACQUISTO N. ACCREDITO';
                    $row['avere'] = $amount ?? 0;
                    break;
                case 'TD05':
                    $row['desc'] = 'FT. ACQUISTO N. ADDEBITO';
                    $row['avere'] = $amount ?? 0;
                    break;
                // case 'TD18':
                //     $row['desc'] = 'FT. ACQUISTO INTRA';
                //     $row['avere'] = $amount ?? 0;
                //     break;
            }

            $param[] = $row;

            if($addVat){
                $param[] = [
                    'order' => \Carbon\Carbon::parse($invoice->invoice_date)->valueOf(),
                    'reg' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                    'fornitore' => [
                        'nome' => $invoice->supplier?->denomination,
                        'pi' => $invoice->supplier?->vat_code,
                        'cf' => $invoice->supplier?->tax_code,
                    ],
                    'num_doc' => $invoice->number,
                    'data_doc' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                    'desc' => $invoice->supplier->country == 'US' 
                            ? 'FT. ACQ. RSM/VATIC./NoUE|Acquisto da non resid. art. 17 <b>#</b>' 
                            : 'FT. ACQUISTO INTRA|Intra Acquisti intracomunitari <b>#</b>',
                    'dare' => (float) $amount * 0.22 ?? 0,
                    'avere' => 0,
                ];
            }
        }

        // 2. Pagamenti con payment_date nel periodo, INDIPENDENTEMENTE dalla fattura collegata
        $passivePayments = \App\Models\PassivePayment::query()
            ->where('company_id', $tenant->id)
            ->with('passiveInvoice.supplier')
            ->whereHas('passiveInvoice', fn($q) => $q->when($input['supplier_id'], fn($qq) => $qq->where('supplier_id', $input['supplier_id'])))
            ->whereHas('passiveInvoice', fn($q) => $q->when($input['docTypes'], fn($qq) => $qq->whereIn('doc_type', $input['docTypes'])))
            ->when($input['from_date'], fn($q) => $q->where('payment_date', '>=', $input['from_date']))
            ->when($input['to_date'], fn($q) => $q->where('payment_date', '<=', $input['to_date']))
            ->get();

        foreach ($passivePayments as $payment) {
            $param[] = [
                'order' => \Carbon\Carbon::parse($payment->payment_date)->valueOf(),
                'reg' => \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y'),
                'fornitore' => [
                    'nome' => $payment->passiveInvoice->supplier?->denomination,
                    'pi' => $payment->passiveInvoice->supplier?->vat_code,
                    'cf' => $payment->passiveInvoice->supplier?->tax_code,
                ],
                // 'num_doc' => $payment->passiveInvoice->number,
                'num_doc' => '',
                // 'data_doc' => $payment->passiveInvoice->invoice_date->format('d/m/Y'),
                'data_doc' => '',
                'desc' => 'PAGAMENTO | FAT. ' . $payment->passiveInvoice->number . ' ' . \Carbon\Carbon::parse($payment->passiveInvoice->invoice_date)->format('d/m/Y'),
                'dare' => $payment->amount ?? 0,
                'avere' => 0,
            ];
        }

        return $param;
    }

    private function getPrecResidue(array $data)
    {
        $type = $data['type'] ?? 'accrual';
        $historicResidue = 0;

        if ($data['supplier_id']) {
            $supplier = Supplier::find($data['supplier_id']);
            $historicResidue = $supplier ? (float) $supplier->residue : 0;
        } else {
            $historicResidue = (float) Supplier::sum('residue');
        }

        if (!$data['from_date']) {
            return $historicResidue;
        }

        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Fatture/quadrature (TD01, TD99) con invoice_date < from_date: dare pregresso
        $invoicesQuery = $tenant
            ->passiveInvoices()
            ->whereNull('parent_id')
            ->whereIn('doc_type', ['0000', 'TD01', 'TD02', 'TD03','TD05','TD06', 'TD24'])
            ->where('passive_invoices.invoice_date', '<', $data['from_date'])
            ->when($data['supplier_id'], fn($q) => $q->where('passive_invoices.supplier_id', $data['supplier_id']));

        $totalAvere = 0;
        foreach ((clone $invoicesQuery)->get() as $invoice) {
            $totalAvere += $invoice->total;
        }

        // 2. Note di credito (TD04) con invoice_date < from_date: avere pregresso
        $creditNotesQuery = $tenant
            ->passiveInvoices()
            ->whereNotNull('parent_id')
            ->where('doc_type', 'TD04')
            ->where('passive_invoices.invoice_date', '<', $data['from_date'])
            ->when($data['supplier_id'], fn($q) => $q->where('passive_invoices.supplier_id', $data['supplier_id']));

        $totalDareNote = 0;
        foreach ($creditNotesQuery->get() as $note) {
            $totalDareNote += $note->total;
        }

        $debitNotesQuery = $tenant
            ->passiveInvoices()
            ->whereNotNull('parent_id')
            ->where('doc_type', 'TD04')
            ->where('passive_invoices.invoice_date', '<', $data['from_date'])
            ->when($data['supplier_id'], fn($q) => $q->where('passive_invoices.supplier_id', $data['supplier_id']));

        $totalAvereNote = 0;
        foreach ($debitNotesQuery->get() as $note) {
            $totalAvereNote += $note->total;
        }

        // 3. Pagamenti: la logica CAMBIA in base al tipo di partitario
        if ($type === 'accrual') {
            // Competenza: contano TUTTI i pagamenti collegati alle fatture pregresse,
            // indipendentemente da payment_date (non compariranno mai come riga separata)
            $totalPayment = (float) (clone $invoicesQuery)->sum('total_payment');
        } else {
            // Esercizio: contano solo i pagamenti con payment_date < from_date, su QUALSIASI fattura,
            // perché i pagamenti dopo from_date compariranno come righe indipendenti
            $totalPayment = (float) \App\Models\PassivePayment::query()
                ->where('company_id', $tenant->id)
                ->whereHas('passiveInvoice', fn($q) => $q->when($data['supplier_id'], fn($qq) => $qq->where('supplier_id', $data['supplier_id'])))
                ->where('payment_date', '<', $data['from_date'])
                ->sum('amount');
        }

        $residue = $historicResidue + $totalAvere - $totalDareNote + $totalAvereNote - $totalPayment;

        return (float) $residue;
    }

    private function closeOpen(array $data, array $temp)
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

    private function generatePdfOutput(array $data, float $residue, array $param, Company $tenant)
    {
        $supplierName = $data['supplier_id'] ? '_' . Supplier::find($data['supplier_id'])->denomination : '';
        $type = $data['type'] === 'accrual' ? '_Competenza' : '_Esercizio';
        $span = '';
        $from_formatted = $data['from_date'] ? (new DateTime($data['from_date']))->format('d-m-Y') : null;
        $to_formatted = $data['to_date'] ? (new DateTime($data['to_date']))->format('d-m-Y') : null;
        if ($data['from_date'] && $data['to_date']) $span .= "_Dal {$from_formatted} al {$to_formatted}";
        else if ($data['from_date']) $span .= "_Dal {$from_formatted}";
        else if ($data['to_date']) $span .= "_Fino al {$to_formatted}";
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
        }, "Partitario{$supplierName}{$type}{$span}.pdf");
    }

    // Metodo per generare output Excel
    protected function generateExcelOutput(array $data, float $residue, array $param)
    {
        // Prepara i dati per Excel
        $excelData = [];

        // Header
        $excelData[] = [
            'Data Reg.',
            'Fornitore',
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

        $supplierName = $data['supplier_id'] ? '_' . Supplier::find($data['supplier_id'])->denomination : '';
        $type = $data['type'] === 'accrual' ? '_Competenza' : '_Esercizio';
        $span = '';
        $from_formatted = $data['from_date'] ? (new DateTime($data['from_date']))->format('d-m-Y') : null;
        $to_formatted = $data['to_date'] ? (new DateTime($data['to_date']))->format('d-m-Y') : null;
        if ($data['from_date'] && $data['to_date']) $span .= "_Dal {$from_formatted} al {$to_formatted}";
        else if ($data['from_date']) $span .= "_Dal {$from_formatted}";
        else if ($data['to_date']) $span .= "_Fino al {$to_formatted}";

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
        }, "Partitario{$supplierName}{$type}{$span}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Partitario_{$supplierName}_{$type}{$span}.xlsx"'
        ]);
    }
}
