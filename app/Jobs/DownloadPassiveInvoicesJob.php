<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\PassiveDownload;
use App\Models\User;
use App\Services\AndxorSoapService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DownloadPassiveInvoicesJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Un import fallito viene annullato per intero dalla transazione:
    // un nuovo tentativo automatico rifarebbe da capo tutte le chiamate SOAP,
    // quindi si preferisce lasciare all'utente la decisione di rilanciare.
    public $tries = 1;
    public $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $data,
        public int $companyId,
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AndxorSoapService $soapService): void
    {
        try {
            Log::info("Inizio scarico fatture passive per company {$this->companyId}");

            $company = Company::findOrFail($this->companyId);

            // In coda non esiste il tenant del pannello: lo si passa esplicitamente.
            $soapService->forCompany($company);

            // Il model PassiveInvoice registra in creazione l'utente autenticato:
            // senza questo le fatture importate resterebbero senza user_id.
            if ($this->userId) {
                Auth::onceUsingId($this->userId);
            }

            $response = $soapService->downloadPassive($this->data);

            if (!$response instanceof PassiveDownload) {
                throw new \Exception('Risposta non valida dal servizio di scarico fatture passive.');
            }

            Log::info("Scarico fatture passive completato per company {$this->companyId}: {$response->new_invoices} fatture, {$response->new_suppliers} fornitori");

            if ($this->userId) {
                Notification::make()
                    ->title($this->buildTitle($response))
                    ->body($this->buildBody($response))
                    ->success()
                    ->sendToDatabase(User::find($this->userId));
            }

        } catch (\Exception $e) {
            Log::error("Errore scarico fatture passive per company {$this->companyId}: {$e->getMessage()}");

            if ($this->userId) {
                Notification::make()
                    ->title('Errore nello scarico delle fatture passive')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->sendToDatabase(User::find($this->userId));
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Scarico fatture passive fallito per company {$this->companyId}: {$exception->getMessage()}");

        if ($this->userId) {
            Notification::make()
                ->title('Scarico fatture passive fallito')
                ->body('Nessuna fattura è stata importata. Riprovare o contattare l\'assistenza.')
                ->danger()
                ->persistent()
                ->sendToDatabase(User::find($this->userId));
        }
    }

    private function buildTitle(PassiveDownload $download): string
    {
        return ($download->new_suppliers || $download->new_invoices)
            ? 'Fatture passive scaricate con successo.'
            : 'Procedura completata.';
    }

    private function buildBody(PassiveDownload $download): string
    {
        $msg = '';

        if ($download->new_suppliers == 1) {
            $msg .= 'Inserito ' . $download->new_suppliers . ' nuovo fornitore.<br> ';
        } elseif ($download->new_suppliers > 1) {
            $msg .= 'Inseriti ' . $download->new_suppliers . ' nuovi fornitori.<br> ';
        }

        if ($download->new_invoices == 1) {
            $msg .= 'Scaricata ' . $download->new_invoices . ' nuova fattura passiva.';
        } elseif ($download->new_invoices > 1) {
            $msg .= 'Scaricate ' . $download->new_invoices . ' nuove fatture passive.';
        }

        return $msg !== '' ? $msg : 'Nessuna nuova fattura o fornitore scaricato.';
    }
}
