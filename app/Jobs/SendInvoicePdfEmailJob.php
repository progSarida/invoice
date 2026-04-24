<?php

namespace App\Jobs;

use App\Mail\InvoicePdfMailable;
use App\Models\Invoice;
use App\Services\InvoiceEmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use Exception;

class SendInvoicePdfEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $backoff = [60, 300, 900]; // Retry dopo 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Invoice $invoice,
        public string $recipientEmail,
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceEmailService $emailService): void
    {
        try {
            Log::info("Inizio invio PDF fattura {$this->invoice->id} a {$this->recipientEmail}");

            // 1. Imposta l'account email dal Sender della company
            $emailService->setAccountFromCompany($this->invoice->company);
            $account = $emailService->getAccount();

            // 2. Genera il PDF
            $pdfData = $this->generateInvoicePdf();

            // 3. Configura mailer dinamico
            $mailerName = "invoice_smtp_" . $this->invoice->id;
            $config = $account->out_mail_server == 'smtp-pc.aruba.it'
                ? $account->getSmtpMailerConfigSarida()
                : $account->getSmtpMailerConfig();

            Config::set("mail.mailers.{$mailerName}", $config);
            Mail::purge($mailerName);

            // 4. Crea e invia il Mailable
            $mailable = new InvoicePdfMailable(
                invoice: $this->invoice,
                pdfContent: $pdfData['content'],
                pdfFilename: $pdfData['filename'],
                fromAddress: $account->getFromAddress(),
                fromName: $account->getFromName()
            );

            $sentMessage = Mail::mailer($mailerName)
                ->to($this->recipientEmail)
                ->send($mailable);

            $messageId = $sentMessage->getMessageId();

            Log::info("PDF fattura {$this->invoice->id} inviato con successo", [
                'message_id' => $messageId,
                'recipient' => $this->recipientEmail
            ]);

            // 5. Notifica successo
            if ($this->userId) {
                Notification::make()
                    ->title('Email inviata con successo')
                    ->body("Fattura {$this->invoice->getNewInvoiceNumber()} inviata a {$this->invoice->client->denomination}")
                    ->success()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $isIpBlocked = str_contains($errorMessage, '554') || str_contains($errorMessage, '5.7.1');

            Log::error("Errore invio PDF fattura {$this->invoice->id}", [
                'recipient' => $this->recipientEmail,
                'error' => $errorMessage,
                'attempt' => $this->attempts(),
                'is_blocked' => $isIpBlocked
            ]);

            // Notifica errore all'utente
            if ($this->userId) {
                Notification::make()
                    ->title('Errore invio email')
                    ->body("Fattura {$this->invoice->getNewInvoiceNumber()}: {$errorMessage}")
                    ->danger()
                    ->persistent()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            if ($isIpBlocked) {
                $this->fail($e);
                return;
            }

            throw $e;
        }
    }

    /**
     * Genera il PDF della fattura
     */
    private function generateInvoicePdf(): array
    {
        // Genera i dati necessari per il PDF (stesso codice di stampa_pdf)
        $vats = $this->invoice->vatResume();
        $funds = $this->invoice->getFundBreakdown();

        if (count($funds) > 0) {
            $vats = $this->invoice->updateResume($vats, $funds);
        }

        $grouped = collect($vats)
            ->groupBy('%')
            ->where('auto', false)
            ->map(function ($items, $percent) {
                return [
                    '%' => $percent,
                    'taxable' => $items->sum('taxable'),
                    'vat' => $items->sum('vat'),
                    'total' => $items->sum('total'),
                    'norm' => $items->first()['norm'],
                    'free' => $items->first()['free'],
                ];
            })
            ->values()
            ->toArray();

        // Genera il PDF
        $pdf = Pdf::loadView('pdf.invoice_courtesy', [
            'invoice' => $this->invoice,
            'vats' => $grouped,
            'funds' => $funds,
        ]);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions(['margin-top' => 0]);

        return [
            'content' => $pdf->output(),
            'filename' => "fattura-{$this->invoice->printNumber()}.pdf"
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error("Invio PDF fattura fallito definitivamente", [
            'invoice_id' => $this->invoice->id,
            'recipient' => $this->recipientEmail,
            'error' => $exception?->getMessage(),
        ]);

        if ($this->userId) {
            Notification::make()
                ->title('Invio email fallito')
                ->body("Email fattura {$this->invoice->getNewInvoiceNumber()} non inviata dopo {$this->tries} tentativi.")
                ->danger()
                ->persistent()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
