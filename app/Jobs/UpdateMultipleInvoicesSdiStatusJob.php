<?php

namespace App\Jobs;

use App\Enums\SdiStatus;
use App\Models\Invoice;
use App\Models\SdiRequest;
use App\Services\AndxorSoapService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UpdateMultipleInvoicesSdiStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600; // 10 minuti per operazioni batch
    public $backoff = [120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $password,
        public int $companyId,
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AndxorSoapService $soapService): void
    {
        try {
            Log::info("Inizio aggiornamento massivo stati SDI per company {$this->companyId}");

            // Recupera le fatture da aggiornare
            $list = Invoice::where('flow', 'out')
                ->where('company_id', $this->companyId)
                ->whereNotIn('sdi_status', ['rifiutata', 'accettata', 'decorrenza_termini', 'scartata', 'mancata_consegna'])
                ->where(function ($query) {
                    $query->whereNotNull('sdi_code')
                        ->orWhere('sdi_status', 'generata');
                })
                ->get();

            if ($list->isEmpty()) {
                Log::info("Nessuna fattura da aggiornare per company {$this->companyId}");

                if ($this->userId) {
                    Notification::make()
                        ->title('Nessuna fattura da aggiornare')
                        ->warning()
                        ->sendToDatabase(\App\Models\User::find($this->userId));
                }
                return;
            }

            $totalInvoices = $list->count();
            $successCount = 0;
            $errorCount = 0;

            Log::info("Trovate {$totalInvoices} fatture da aggiornare");

            foreach ($list as $invoice) {
                try {
                    Log::info("Aggiornamento fattura {$invoice->id}");
                    $soapService->updateStatus($invoice, $this->password);
                    $successCount++;

                    // Pausa per evitare rate limiting
                    sleep(1);
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Errore aggiornamento fattura {$invoice->id}: {$e->getMessage()}");
                    continue; // Continua con la prossima fattura
                }
            }

            // Registra la richiesta batch
            SdiRequest::create([
                'company_id' => $this->companyId,
                'request_date' => today()->format('Y-m-d'),
                'sdi_request_type' => 'mass',
                'invoice_id' => null
            ]);

            Log::info("Aggiornamento massivo completato: {$successCount} successi, {$errorCount} errori su {$totalInvoices} totali");

            // Notifica risultato
            if ($this->userId) {
                $notification = Notification::make()
                    ->title('Aggiornamento stati SDI completato')
                    ->body("Aggiornate {$successCount} fatture su {$totalInvoices}" .
                           ($errorCount > 0 ? ". {$errorCount} errori." : ""));

                if ($errorCount > 0) {
                    $notification->warning();
                } else {
                    $notification->success();
                }

                $notification->sendToDatabase(\App\Models\User::find($this->userId));
            }

        } catch (\Exception $e) {
            Log::error("Errore aggiornamento massivo stati SDI: {$e->getMessage()}");

            if ($this->userId) {
                Notification::make()
                    ->title('Errore aggiornamento massivo')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Aggiornamento massivo fallito dopo {$this->tries} tentativi: {$exception->getMessage()}");

        if ($this->userId) {
            Notification::make()
                ->title('Aggiornamento massivo fallito')
                ->body("Impossibile completare l'aggiornamento massivo degli stati SDI")
                ->danger()
                ->persistent()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
