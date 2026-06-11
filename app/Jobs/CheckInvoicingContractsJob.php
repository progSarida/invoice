<?php

namespace App\Jobs;

use App\Enums\ClientType;
use App\Models\User;
use App\Models\NewContract;
use App\Enums\InvoicingCicle;
use App\Models\BankAccount;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckInvoicingContractsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenant;
    public $user;

    /**
     * Create a new job instance.
     */
    public function __construct($tenant, $user)
    {
        $this->user = $user;
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $activeContracts = $this->getActiveContractsData();
        $contracts = $this->getInvoicingContracts($activeContracts);
Log::info("TEST");
        foreach($contracts['to_invoice'] as $contract) {
            // $users = $contract->company->users;
            // foreach ($users as $user) {
                // $user->notify(
                    Notification::make()
                        ->title('Il contratto con ' . $contract->client->denomination . ' (' . implode('-', $contract->tax_types) . ' - ' . $contract->cig_code . ') ' . 'deve essere fatturato')
                        // ->body('TESTBODY')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->warning()
                        ->sendToDatabase($this->user);
                // );
            // }
        }

        foreach($contracts['partial'] as $contract) {
            // $users = $contract->company->users;
            // foreach ($users as $user) {
                // $user->notify(
                    Notification::make()
                        ->title('Il contratto con ' . $contract->client->denomination . ' (' . implode('-', $contract->tax_types) . ' - ' . $contract->cig_code . ') ' . 'ha una fattura parzialmente stornata')
                        // ->body('TESTBODY')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->warning()
                        ->sendToDatabase($this->user);
                // );
            // }
        }
    }

    private function getActiveContracts()                                                       // recupera i contratti ancora attivi
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::where('start_validity_date', '<=', $today)                    // seleziono i contratti base
            ->where('company_id', $this->tenant->id)
            ->where('closed', false)
            // ->where(function ($query) use ($today) {
            //     $query->whereNull('end_validity_date')
            //         ->orWhere('end_validity_date', '>=', $today);
            // })
            ->get();

        return $contracts;
    }

    private function getActiveContractsData()                                                   // recupera i contratti ancora attivi con data, numero, sezionario e anno dell'ultima fattura emessa
    {
        $today = now()->format('Y-m-d');

        $contracts = NewContract::where('start_validity_date', '<=', $today)                    // seleziono i contratti base
            ->where('company_id', $this->tenant->id)
            ->where('closed', false)
            // ->where(function ($query) use ($today) {
            //     $query->whereNull('end_validity_date')
            //         ->orWhere('end_validity_date', '>=', $today);
            // })
            ->get();

        $activeContracts = collect();

        foreach ($contracts as $contract) {                                                     // per ogni contratto calcoliamo le informazioni aggiuntive

            $lastInvoice = Invoice::where('contract_id', $contract->id)                     // trovo l'ultima fattura
                ->where('flow', 'out')
                ->orderBy('invoice_date', 'desc')
                ->first();
            $notRound = BankAccount::find($lastInvoice?->bank_account_id)?->name != 'Giroconto';

            $query = Invoice::where('contract_id', $contract->id)                               // calcolo il totale fatturato
                ->where('flow', 'out');                                                         // non necessario perchè le invoice legate ai NewContract sono tutte con flow = 'out'
            if($contract->client?->type == ClientType::PUBLIC && $notRound)
                $totalInvoiced = $query->sum('no_vat_total') ?? 0;                              // se contratto con PA sommo il totale senza iva
            else
                $totalInvoiced = $query->sum('total') ?? 0;                                     // se contratto con privato sommo il totale con iva

            if ($contract->amount > $totalInvoiced) {                                           // verifico se il contratto soddisfa la condizione
                                                                                                // aggiungo i dati calcolati al contratto
                $contract->total_invoiced = $totalInvoiced;                                     // totale fatturato
                $contract->last_invoice_date = $lastInvoice?->invoice_date;                     // data ultima fattura
                $contract->last_invoice_number = $lastInvoice?->number;                         // numero ultima fattura
                $contract->last_invoice_sectional_id = $lastInvoice?->sectional_id;             // sezionario ultima fattura
                $contract->last_invoice_year = $lastInvoice?->year;                             // anno ultima fattura

                if($contract->client?->type?->value == 'public' && $notRound)
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
        $partialInvoicingContracts = collect();

        foreach($activeContracts as $contract) {
            $invoicingCycle = $contract->invoicing_cycle;
Log::info("Contratto: {$contract->id} ---------------------------------------------------------------------------------------------");
Log::info("Data ultima fattura: {$contract->last_invoice_date}");
            if ($invoicingCycle === null) { continue; }                                         // se il ciclo di fatturazione è null salto il contratto

            if ($invoicingCycle instanceof InvoicingCicle) { $cycle = $invoicingCycle; }
            else { $cycle = InvoicingCicle::from($invoicingCycle); }

            $invoiceTime = match($cycle) {                                                      // controllo se il termine di fatturazione è passato
                InvoicingCicle::ONCE => $this->checkOnceInvoicing($contract),
                InvoicingCicle::MONTHLY => $this->checkMonthlyInvoicing($contract),
                InvoicingCicle::BIMONTHLY => $this->checkBimonthlyInvoicing($contract),
                InvoicingCicle::QUARTERLY => $this->checkQuarterlyInvoicing($contract),
                InvoicingCicle::SEMIANNUALLY => $this->checkSemiannuallyInvoicing($contract),
                InvoicingCicle::ANNUALLY => $this->checkAnnuallyInvoicing($contract),
            };
$toInvoice = $invoiceTime ? 'Si' : 'No';
Log::info("Da fatturare: {$toInvoice}");
            if ($invoiceTime) {
                if($contract->last_invoice_notes > 0 && $contract->last_invoice_notes < $contract->last_invoice_total && !$this->notificationExpired($contract))
                    $partialInvoicingContracts->push($contract);                                // se notes non è zero ma è minore di total e lo storno ha meno di sei mesi => partialInvoicingContracts
                else
                    $invoicingContracts->push($contract);                                       // se notes è zero o (maggiore o uguale a total) => invoicingContract
            }
        }

        $output['to_invoice'] = $invoicingContracts;
        $output['partial'] = $partialInvoicingContracts;
// dd($output);
        return $output;
    }

    private function checkOnceInvoicing($contract): bool
    {
        $actualInvoices = Invoice::where('contract_id', $contract->id)->count();                // Controllo quante fatture sono state effettivamente emesse

        return $actualInvoices < 1;                                                             // Controllo che si debba creare la fattura attuale
    }

    private function checkMonthlyInvoicing($contract): bool
    {
        $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 1);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 1);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkBimonthlyInvoicing($contract): bool
    {
       $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 2);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 2);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkQuarterlyInvoicing($contract): bool
    {
       $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 3);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 3);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkSemiannuallyInvoicing($contract): bool
    {
       $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 6);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 6);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function checkAnnuallyInvoicing($contract): bool
    {
        $today = now();
        $startDate = Carbon::parse($contract->start_validity_date);

        $monthsSinceStart = $startDate->diffInMonths($today);                                   // Calcolo i mesi passati dall'inizio del contratto

        $expectedPeriod = floor($monthsSinceStart / 12);                                         // Calcolo quale periodo dovrei aver fatturato (es: mese 0, 1, 2, 3...)

        if ($expectedPeriod == 0) {
            return false;                                                                       // Non è ancora il momento di fatturare
        }

        if (is_null($contract->last_invoice_date)) {
            return true;                                                                        // Nessuna fattura emessa ma dovrei averne almeno una
        }

        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        $lastInvoicedPeriod = floor($startDate->diffInMonths($lastInvoiceDate) / 12);            // Calcolo fino a quale periodo ho fatturato

        return $lastInvoicedPeriod < $expectedPeriod;                                           // Controllo se devo fatturare il periodo attuale
    }

    private function notificationExpired($contract): bool
    {
        $lastInvoiceDate = Carbon::parse($contract->last_invoice_date);
        return $lastInvoiceDate->diffInMonths(now()) > 6;                                       // controllo che siano passati sei mesi dalla data dell'ultima fattura
    }
}
