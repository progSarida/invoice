<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Pages;

use Carbon\Carbon;
use App\Models\User;
use Filament\Actions;
use App\Models\Invoice;
use App\Models\NewContract;
use App\Enums\InvoicingCicle;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\ExportAction;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Exports\NewInvoiceExporter;
use App\Filament\Company\Resources\NewInvoiceResource;
use Illuminate\Support\Facades\DB;

class ListNewInvoices extends ListRecords
{
    protected static string $resource = NewInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkInvoicing')
                // ->hidden()
                ->label('Controllo contratti da fatturare')
                ->action(function () {
                    $activeContracts = $this->getActiveContractsData();
                    $contracts = $this->getInvoicingContracts($activeContracts);
                    $user = Auth::user();
                    foreach ($contracts['to_invoice'] as $contract) {
                        $user->notify(
                            Notification::make()
                                ->title('Il contratto con ' . $contract->client->denomination . ' (' . $contract->tax_type->getLabel() . ' - ' . $contract->cig_code . ') ' . 'deve essere fatturato')
                                // ->body('TESTBODY')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->warning()
                                ->toDatabase(),
                        );
                    }
                    foreach ($contracts['partial'] as $contract) {
                        $user->notify(
                            Notification::make()
                                ->title('Il contratto con ' . $contract->client->denomination . ' (' . $contract->tax_type->getLabel() . ' - ' . $contract->cig_code . ') ' . 'ha una fattura parzialmente stornata')
                                // ->body('TESTBODY')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->warning()
                                ->toDatabase(),
                        );
                    }
                }),
            Actions\CreateAction::make()
                /*->hidden(function () {
                    // Fatture rifiutate
                    $refusedHide = false;
                    $refused = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'rifiutata');
                    $refusedE = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'rifiuto_emesso');
                    // Fatture scartate
                    $discardedHide = false;
                    $discarded = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'scartata');
                    // Fatture non inviate da due giorni
                    $lateHide = false;
                    $late = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'da_inviare')->where('invoice_date', '<', Carbon::now()->subDays(2));
                    // Fatture senza esito inviate da più di 3 giorni
                    $silentHide = false;
                    $silent = \App\Models\Invoice::where('flow', 'out')->whereIn('sdi_status', ['inviata', 'trasmessa_sdi'])->where('sdi_date', '<', Carbon::now()->subDays(3));

                    if ($silent->count() > 0) {                                                                   // blocco per fatture senza esito inviate da più di 3 giorni
                        Notification::make('silent_status')
                            ->title('Sono presenti fatture senza esito da oltre 3 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $silentHide = true;
                    }

                    if ($late->count() > 0) {                                                                   // blocco per fatture con data vecchia di più di 2 giorni
                        Notification::make('late_status')
                            ->title('Sono presenti fatture da inviare da almeno 2 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $lateHide = true;
                    }

                    if ($refusedE->count() > 0) {                                                               // link fatture rifiutate
                        $invoicesR = $refusedE->get();
                        foreach ($invoicesR as $index => $el) {
                            if (!\App\Models\Invoice::where('parent_id', $el->id)->exists()) {
                                Notification::make('refused_credit_note_' . $el->id)
                                    ->title('Emettere la nota di credito per la fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year)
                                    ->color('gray')
                                    ->icon('phosphor-warning-circle-light')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai alla fattura')
                                            ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                        }
                        $refusedHide = true;
                    }

                    if ($refused->count() > 0) {                                                                // blocco fatture rifiutate
                        Notification::make('refused_status')
                            ->title('Sono presenti fatture rifiutate<br>(Status: NE EC02 - Rifiuto)<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $refusedHide = true;
                    }

                    if ($discarded->count() > 0) {                                                              // link fatture scartate
                        $invoicesD = $discarded->get();
                        foreach ($invoicesD as $index => $el) {
                            $daysLeft = now()->diffInDays($el->invoice_date, true);
                            // if($el->id = 6348) dd($daysLeft);
                            if($daysLeft <= 12){
                                Notification::make('discarded_manage_' . $el->id)
                                    ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                            Correggere i dati errati e reinviare<br>")
                                    ->color('gray')
                                    ->icon('phosphor-warning-circle-light')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai alla fattura')
                                            ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                            else{
                                Notification::make('discarded_manage_' . $el->id)
                                    ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                            Modificare stato (in Scarto validato) ed emettere una nuova fattura<br>[Fattura collegata alla numero
                                            " . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . " del " . \Carbon\Carbon::parse($el->invoice_date)->format('d/m/Y') . " scartata dallo SDI]")
                                    ->color('gray')
                                    ->icon('phosphor-warning-circle-light')
                                    ->persistent()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('edit')
                                            ->label('Vai alla fattura')
                                            ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                            ->color('warning'),
                                    ])
                                    ->send();
                            }
                        }
                        $refusedHide = true;
                    }

                    if ($discarded->count() > 0) {                                                              // blocco per fatture scartate
                        Notification::make('discarded_status')
                            ->title('Sono presenti fatture scartate<br>(Status: NS - Notifica di scarto)<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                            ->color('danger')
                            ->icon('gmdi-block')
                            ->persistent()
                            ->send();
                        $discardedHide = true;
                    }

                    return ($refusedHide || $discardedHide || $lateHide || $silentHide);
                })*/
                ->hidden(function () {
                    // Controlli per bloccare l'inserimento di nuove fatture
                    $refusedHide = $this->refusedHide();                                                        // controllo fatture rifiutate
                    $discardedHide = $this->discardedHide();                                                    // controllo fatture scartate
                    $lateHide = $this->lateHide();                                                              // controllo fatture non inviate
                    $silentHide = $this->silentHide();                                                          // controllo fatture senza esito

                    // Controllo su contratti da fatturare in base a periodicità
                    // $activeContracts = $this->getActiveContracts();                                             // recupero contratti attivi
                    // $activeContracts = $this->getActiveContractsData();                                         // recupero contratti attivi con dati ultima fattura
                    // $invoicingContracts = $this->getInvoicingContracts($activeContracts);                       // recupero i contratti da fatturare

                    // creo notifica su tabella notifications

                    return ($refusedHide || $discardedHide || $lateHide || $silentHide);
                })
                // ->keyBindings(['alt+n'])
                ,
            Actions\Action::make('stampa')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco fatture')
                // ->iconButton() // mostro solo icona
                ->color('primary')
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get(); // recupero risultato della query
                    $filters = $livewire->tableFilters ?? []; // recupero i filtri
                    $search = $livewire->tableSearch ?? null; // recupero la ricerca

                    $fileName = 'Fatture_' . \Carbon\Carbon::today()->format('d-m-Y') . '.pdf';

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
                                Blade::render('pdf.new_invoices', [
                                    'invoices' => $records,
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
            ExportAction::make('esporta')
                ->icon('phosphor-export')
                ->label('Esporta')
                ->color('primary')
                ->exporter(NewInvoiceExporter::class)
                // ->keyBindings(['alt+e'])

                ,
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }

    private function refusedHide()                                                              // controllo fatture rifiutate
    {
        $refusedHide = false;
        $refused = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'rifiutata');
        $refusedE = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'rifiuto_emesso');

        if ($refused->count() > 0) {
            Notification::make('refused_status')
                ->title('Sono presenti fatture rifiutate<br>(Status: NE EC02 - Rifiuto)<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                ->color('danger')
                ->icon('gmdi-block')
                ->persistent()
                ->send();
            return true;
        }

        if ($refusedE->count() > 0) {                                                           // link fatture rifiutate
            $invoicesR = $refusedE->get();
            foreach ($invoicesR as $index => $el) {
                if (!\App\Models\Invoice::where('parent_id', $el->id)->exists()) {
                    Notification::make('refused_credit_note_' . $el->id)
                        ->title('Emettere la nota di credito per la fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year)
                        ->color('gray')
                        ->icon('phosphor-warning-circle-light')
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('edit')
                                ->label('Vai alla fattura')
                                ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                ->color('warning'),
                        ])
                        ->send();
                }
            }
            return true;
        }

        return false;
    }

    private function discardedHide()                                                            // controllo fatture scartate
    {
        $discarded = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'scartata');

        if ($discarded->count() > 0) {                                                          // link fatture scartate
            $invoicesD = $discarded->get();
            foreach ($invoicesD as $index => $el) {
                $daysLeft = now()->diffInDays($el->invoice_date, true);
                // if($el->id = 6348) dd($daysLeft);
                if($daysLeft <= 12){
                    Notification::make('discarded_manage_' . $el->id)
                        ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                Correggere i dati errati e reinviare<br>")
                        ->color('gray')
                        ->icon('phosphor-warning-circle-light')
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('edit')
                                ->label('Vai alla fattura')
                                ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                ->color('warning'),
                        ])
                        ->send();
                }
                else{
                    Notification::make('discarded_manage_' . $el->id)
                        ->title('La fattura ' . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . "/" . $el->year . " è stata scartata<br>
                                Modificare stato (in Scarto validato) ed emettere una nuova fattura<br>[Fattura collegata alla numero
                                " . str_pad($el->number, 3, '0', STR_PAD_LEFT) . "/" . $el->sectional->description . " del " . \Carbon\Carbon::parse($el->invoice_date)->format('d/m/Y') . " scartata dallo SDI]")
                        ->color('gray')
                        ->icon('phosphor-warning-circle-light')
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('edit')
                                ->label('Vai alla fattura')
                                ->url(NewInvoiceResource::getUrl('edit', ['record' => $el->id]))
                                ->color('warning'),
                        ])
                        ->send();
                }
            }
            return true;
        }

        return false;
    }

    private function lateHide()                                                                 // controllo fatture non inviate da due giorni
    {
        $late = \App\Models\Invoice::where('flow', 'out')->where('sdi_status', 'da_inviare')->where('invoice_date', '<', Carbon::now()->subDays(2));

        if ($late->count() > 0) {                                                                   // blocco per fatture con data vecchia di più di 2 giorni
            Notification::make('late_status')
                ->title('Sono presenti fatture da inviare da almeno 2 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                ->color('danger')
                ->icon('gmdi-block')
                ->persistent()
                ->send();
            return true;
        }

        return false;
    }

    private function silentHide()                                                               // controllo fatture senza esito inviate da più di 3 giorni
    {
        $silent = \App\Models\Invoice::where('flow', 'out')->whereIn('sdi_status', ['inviata', 'trasmessa_sdi'])->where('sdi_date', '<', Carbon::now()->subDays(3));

        if ($silent->count() > 0) {                                                             // blocco per fatture senza esito inviate da più di 3 giorni
            Notification::make('silent_status')
                ->title('Sono presenti fatture senza esito da oltre 3 giorni<br>L\'inserimento di nuove fatture sarà bloccato fino alla loro gestione')
                ->color('danger')
                ->icon('gmdi-block')
                ->persistent()
                ->send();
            return true;
        }

        return false;
    }

    private function getActiveContracts()                                                       // recupera i contratti ancora attivi
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::leftJoin('invoices', function($join) {                        // recupero i contratti attivi
                $join->on('new_contracts.id', '=', 'invoices.contract_id')
                    ->where('invoices.flow', '=', 'out');                                       // controllo solo sulle fatture nuove
            })
            ->select('new_contracts.*')
            ->selectRaw('COALESCE(SUM(invoices.total), 0) as total_invoiced')
            ->where('new_contracts.start_validity_date', '<=', $today)                          // recupero i contratti iniziati
            ->where(function ($query) use ($today) {
                $query->whereNull('new_contracts.end_validity_date')                            // recupero i contratti senza data di termine
                    ->orWhere('new_contracts.end_validity_date', '>=', $today);                 // recupero i contratti per cui non si è raggiunta la data di termine
            })
            ->groupBy('new_contracts.id')
            ->havingRaw('new_contracts.amount > total_invoiced')                                // recupero quelli per cui non si è raggiunto l'importo massimo
            ->get();

        // dd($contracts);

        return $contracts;
    }

    private function getActiveContractsData()                                                   // recupera i contratti ancora attivi con data, numero, sezionario e anno dell'ultima fattura emessa
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::where('start_validity_date', '<=', $today)                    // seleziono i contratti base
            ->where(function ($query) use ($today) {
                $query->whereNull('end_validity_date')
                    ->orWhere('end_validity_date', '>=', $today);
            })
            ->get();

        $activeContracts = collect();

        foreach ($contracts as $contract) {                                                     // per ogni contratto calcoliamo le informazioni aggiuntive

            $totalInvoiced = Invoice::where('contract_id', $contract->id)                       // calcolo il totale fatturato
                // ->where('flow', 'out')                                                          // non necessario perchè le invoice legate ai NewContract sono tutte con flow = 'out'
                ->sum('total') ?? 0;

            if ($contract->amount > $totalInvoiced) {                                           // verifico se il contratto soddisfa la condizione

                $lastInvoice = Invoice::where('contract_id', $contract->id)                     // rovo l'ultima fattura
                    ->where('flow', 'out')
                    ->orderBy('invoice_date', 'desc')
                    ->first();
                                                                                                // aggiungo i dati calcolati al contratto
                $contract->total_invoiced = $totalInvoiced;                                     // totale fatturato
                $contract->last_invoice_date = $lastInvoice?->invoice_date;                     // data ultima fattura
                $contract->last_invoice_number = $lastInvoice?->number;                         // numero ultima fattura
                $contract->last_invoice_sectional_id = $lastInvoice?->sectional_id;             // sezionario ultima fattura
                $contract->last_invoice_year = $lastInvoice?->year;                             // anno ultima fattura
                if($contract->client?->type?->value == 'public')
                    $contract->last_invoice_total = $lastInvoice?->no_vat_total;                // totale senza iva ultima fattura
                else
                    $contract->last_invoice_total = $lastInvoice?->total;                       // totale ultima fattura
                $contract->last_invoice_notes = $lastInvoice?->total_notes;                     // totale note di credito su ultima fattura

                $activeContracts->push($contract);                                              // aggiungo alla collezione dei contratti validi
            }
        }

        // dd($activeContracts);

        return $activeContracts;
    }

    private function getInvoicingContracts($activeContracts)                                    // recupero i contratti da fatturare
    {
        $invoicingContracts = collect();
        $partialinvoicingContracts = collect();

        foreach($activeContracts as $contract) {
            $invoicingCycle = $contract->invoicing_cycle;

            if ($invoicingCycle instanceof InvoicingCicle) { $cycle = $invoicingCycle; } 
            else { $cycle = InvoicingCicle::from($invoicingCycle); }

            $invoiceTime = match($cycle) {                                                      // controllo se il termine di fatturazione è passato
                InvoicingCicle::MONTHLY => $this->checkMonthlyInvoicing($contract),
                InvoicingCicle::BIMONTHLY => $this->checkBimonthlyInvoicing($contract),
                InvoicingCicle::QUARTERLY => $this->checkQuarterlyInvoicing($contract),
                InvoicingCicle::SEMIANNUALLY => $this->checkSemiannuallyInvoicing($contract),
                InvoicingCicle::ANNUALLY => $this->checkAnnuallyInvoicing($contract),
            };

            if ($invoiceTime) {
                if($contract->last_invoice_notes > 0 && $contract->last_invoice_notes < $contract->last_invoice_total)
                    $partialinvoicingContracts->push($contract);                                // se notes non è zero ma è minore di total => partialinvoicingContracts
                else
                $invoicingContracts->push($contract);                                           // se notes è zero o (maggiore o uguale a total) => invoicingContract
            }
        }

        $output['to_invoice'] = $invoicingContracts;
        $output['partial'] = $partialinvoicingContracts;

        return $output;
    }

    private function checkMonthlyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 1;                                        // controllo che sia passato un mese dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 1;                                  // controllo che sia passato un mese dalla data dell'ultima fattura
        }
    }

    private function checkBimonthlyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 2;                                        // controllo che siano passati due mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 2;                                  // controllo che siano passati due mesi dalla data dell'ultima fattura
        }
    }

    private function checkQuarterlyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 3;                                        // controllo siano passati tre mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 3;                                  // controllo che siano passati tre mesi dalla data dell'ultima fattura
        }
    }

    private function checkSemiannuallyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 6;                                        // controllo che siano passati sei mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 6;                                  // controllo che siano passati sei mesi dalla data dell'ultima fattura
        }
    }

    private function checkAnnuallyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $startDate->diffInMonths($today) > 12;                                       // controllo che sia passato un anno dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $lastInvoiceDate->diffInMonths($today) > 12;                                 // controllo che sia passato un anno dalla data dell'ultima fattura
        }
    }
}
