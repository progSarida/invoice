<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\AndxorSoapService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvoiceToSdiJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 120, 300]; // Retry dopo 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Invoice $invoice,
        public string $password,
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AndxorSoapService $soapService): void
    {
        try {
            Log::info("Inizio invio fattura {$this->invoice->id} allo SDI");

            $response = $soapService->sendInvoice($this->invoice, $this->password);

            Log::info("Fattura {$this->invoice->id} inviata con successo. Progressivo: {$response->ProgressivoInvio}");

            // Notifica successo all'utente
            if ($this->userId) {
                Notification::make()
                    ->title('Fattura inviata con successo')
                    ->body("Fattura {$this->invoice->getNewInvoiceNumber()} - Progressivo: {$response->ProgressivoInvio}")
                    ->success()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

        } catch (\Exception $e) {
            Log::error("Errore invio fattura {$this->invoice->id} allo SDI: {$e->getMessage()}");

            // Notifica errore all'utente
            if ($this->userId) {
                Notification::make()
                    ->title('Errore invio fattura')
                    ->body("Fattura {$this->invoice->getNewInvoiceNumber()}: {$e->getMessage()}")
                    ->danger()
                    ->persistent()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            // Rilancia l'eccezione per il retry automatico
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Invio fattura {$this->invoice->id} fallito definitivamente dopo {$this->tries} tentativi: {$exception->getMessage()}");

        if ($this->userId) {
            Notification::make()
                ->title('Invio fattura fallito')
                ->body("Fattura {$this->invoice->getNewInvoiceNumber()} non è stata inviata dopo {$this->tries} tentativi. Contattare l'assistenza.")
                ->danger()
                ->persistent()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
