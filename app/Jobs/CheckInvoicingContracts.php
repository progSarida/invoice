<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\NewContract;
use App\Enums\InvoicingCicle;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CheckInvoicingContracts implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $activeContracts = $this->getActiveContractsData();
        $contracts = $this->getInvoicingContracts($activeContracts);

        foreach($contracts['to_invoice'] as $contract) {
            $users = $contract->company->users;
            foreach ($users as $user) {
                $user->notify(
                    Notification::make()
                        ->title('Il contratto con ' . $contract->client->denomination . ' (' . implode('-', $contract->tax_types) . ' - ' . $contract->cig_code . ') ' . 'deve essere fatturato')
                        // ->body('TESTBODY')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->warning()
                        ->toDatabase(),
                );
            }
        }

        foreach($contracts['partial'] as $contract) {
            $users = $contract->company->users;
            foreach ($users as $user) {
                $user->notify(
                    Notification::make()
                        ->title('Il contratto con ' . $contract->client->denomination . ' (' . implode('-', $contract->tax_types) . ' - ' . $contract->cig_code . ') ' . 'ha una fattura parzialmente stornata')
                        // ->body('TESTBODY')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->warning()
                        ->toDatabase(),
                );
            }
        }
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
            ->where('company_id', Filament::getTenant()->id)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_validity_date')
                    ->orWhere('end_validity_date', '>=', $today);
            })
            ->get();

        $activeContracts = collect();

        foreach ($contracts as $contract) {                                                     // per ogni contratto calcoliamo le informazioni aggiuntive

            $totalInvoiced = Invoice::where('contract_id', $contract->id)                       // calcolo il totale fatturato
                ->where('flow', 'out')                                                          // non necessario perchè le invoice legate ai NewContract sono tutte con flow = 'out'
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
                InvoicingCicle::ONCE => false,
                InvoicingCicle::MONTHLY => $this->checkMonthlyInvoicing($contract),
                InvoicingCicle::BIMONTHLY => $this->checkBimonthlyInvoicing($contract),
                InvoicingCicle::QUARTERLY => $this->checkQuarterlyInvoicing($contract),
                InvoicingCicle::SEMIANNUALLY => $this->checkSemiannuallyInvoicing($contract),
                InvoicingCicle::ANNUALLY => $this->checkAnnuallyInvoicing($contract),
            };

            if ($invoiceTime) {
                if($contract->last_invoice_notes > 0 && $contract->last_invoice_notes < $contract->last_invoice_total && !$this->notificationExpired($contract))
                    $partialinvoicingContracts->push($contract);                                // se notes non è zero ma è minore di total e lo storno ha meno di sei mesi => partialinvoicingContracts
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
            return $today->diffInMonths($startDate) > 1;                                        // controllo che sia passato un mese dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $today->diffInMonths($lastInvoiceDate) > 1;                                  // controllo che sia passato un mese dalla data dell'ultima fattura
        }
    }

    private function checkBimonthlyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $today->diffInMonths($startDate) > 2;                                        // controllo che siano passati due mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $today->diffInMonths($lastInvoiceDate) > 2;                                  // controllo che siano passati due mesi dalla data dell'ultima fattura
        }
    }

    private function checkQuarterlyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $today->diffInMonths($startDate) > 3;                                        // controllo siano passati tre mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $today->diffInMonths($lastInvoiceDate) > 3;                                  // controllo che siano passati tre mesi dalla data dell'ultima fattura
        }
    }

    private function checkSemiannuallyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $today->diffInMonths($startDate) > 6;                                        // controllo che siano passati sei mesi dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $today->diffInMonths($lastInvoiceDate) > 6;                                  // controllo che siano passati sei mesi dalla data dell'ultima fattura
        }
    }

    private function checkAnnuallyInvoicing($contract): bool
    {
        $today = now();

        if (is_null($contract->last_invoice_date)) {                                            // se non ci sono fatture precedenti
            $startDate = Carbon::parse($contract->start_validity_date);
            return $today->diffInMonths($startDate) > 12;                                       // controllo che sia passato un anno dalla data di inizio del contratto
        } else {
            $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
            return $today->diffInMonths($lastInvoiceDate) > 12;                                 // controllo che sia passato un anno dalla data dell'ultima fattura
        }
    }

    private function notificationExpired($contract): bool
    {
        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        return $lastInvoiceDate->diffInMonths(now()) > 6;                                       // controllo che siano passati sei mesi dalla data dell'ultima fattura
    }
}
