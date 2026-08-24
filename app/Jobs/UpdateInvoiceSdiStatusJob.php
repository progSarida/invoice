<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\SdiRequest;
use App\Services\AndxorSoapService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateInvoiceSdiStatusJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Invoice $invoice,
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
            Log::info("Inizio aggiornamento stato SDI per fattura {$this->invoice->id}");

            $response = $soapService->updateStatus($this->invoice, $this->password);

            SdiRequest::create([
                'company_id' => $this->companyId,
                'request_date' => today()->format('Y-m-d'),
                'sdi_request_type' => 'single',
                'invoice_id' => $this->invoice->id
            ]);

            Log::info("Stato SDI aggiornato per fattura {$this->invoice->id}");

            // Notifica successo
            if ($this->userId) {
                Notification::make()
                    ->title('Stato SDI aggiornato')
                    ->body("Fattura {$this->invoice->getNewInvoiceNumber()} aggiornata con successo")
                    ->success()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

        } catch (\Exception $e) {
            Log::error("Errore aggiornamento stato SDI fattura {$this->invoice->id}: {$e->getMessage()}");

            if ($this->userId) {
                Notification::make()
                    ->title('Errore aggiornamento stato')
                    ->body("Fattura {$this->invoice->getNewInvoiceNumber()}: {$e->getMessage()}")
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
        Log::error("Aggiornamento stato fattura {$this->invoice->id} fallito dopo {$this->tries} tentativi: {$exception->getMessage()}");

        if ($this->userId) {
            Notification::make()
                ->title('Aggiornamento stato fallito')
                ->body("Fattura {$this->invoice->getNewInvoiceNumber()}: impossibile aggiornare lo stato SDI")
                ->danger()
                ->persistent()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
