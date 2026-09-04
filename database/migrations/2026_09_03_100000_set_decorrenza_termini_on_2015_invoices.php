<?php

use App\Enums\SdiStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const CODE = '00000000000';                                                             // codice fittizio (service_code / sdi_code / code)
    private const DATE = '2026-09-03';                                                              // data della sanatoria
    private const DESCRIPTION = 'Sanatoria massiva';                                                // marcatore delle notifiche create qui

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->sanitize(2015);                                                                      // fatture 2015 rimaste da inviare verso pubbliche amministrazioni: portale SDI le considera scadute, quindi le chiudiamo con decorrenza termini
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->revert();
    }

    /**
     * Chiude le fatture di un anno rimaste in un dato stato: crea per ognuna una notifica sdi
     * e ne allinea i dati sdi. Restituisce il numero di fatture trattate.
     */
    private function sanitize(
        int $year,
        SdiStatus $fromStatus = SdiStatus::DA_INVIARE,
        SdiStatus $toStatus = SdiStatus::DECORRENZA_TERMINI,
        string $code = self::CODE,
        string $date = self::DATE,
        string $description = self::DESCRIPTION,
    ): int {
        return DB::transaction(function () use ($year, $fromStatus, $toStatus, $code, $date, $description) {

            $invoiceIds = DB::table('invoices')                                                     // fatture da sanare
                ->where('year', $year)
                ->where('sdi_status', $fromStatus->value)
                ->pluck('id');

            if ($invoiceIds->isEmpty()) {
                $this->report("Sanatoria {$year}: nessuna fattura in stato '{$fromStatus->value}'.");

                return 0;
            }

            $notifications = $invoiceIds->map(fn ($id) => [                                         // una notifica per fattura
                'invoice_id'  => $id,
                'code'        => $code,
                'status'      => $toStatus->value,
                'date'        => $date,
                'description' => $description,
                'created_at'  => $date,
                'updated_at'  => $date,
            ])->all();

            foreach (array_chunk($notifications, 500) as $chunk) {
                DB::table('sdi_notifications')->insert($chunk);
            }

            DB::table('invoices')                                                                   // dati sdi allineati alla notifica
                ->whereIn('id', $invoiceIds)
                ->update([
                    'service_code' => $code,
                    'sdi_code'     => $code,
                    'sdi_status'   => $toStatus->value,
                    'sdi_info'     => $description,
                    'sdi_date'     => $date,
                    'updated_at'   => $date,
                ]);

            $count = $invoiceIds->count();

            $this->report("Sanatoria {$year}: {$count} fatture portate da '{$fromStatus->value}' a '{$toStatus->value}'.");

            return $count;
        });
    }

    /**
     * Annulla una lavorazione: cancella le notifiche riconoscibili da descrizione e data
     * e riporta le fatture collegate allo stato di partenza.
     */
    private function revert(
        SdiStatus $toStatus = SdiStatus::DA_INVIARE,
        string $date = self::DATE,
        string $description = self::DESCRIPTION,
    ): int {
        return DB::transaction(function () use ($toStatus, $date, $description) {

            $notifications = DB::table('sdi_notifications')                                         // solo le notifiche create dalla lavorazione
                ->where('description', $description)
                ->where('date', $date);

            $invoiceIds = (clone $notifications)->pluck('invoice_id');

            if ($invoiceIds->isEmpty()) {
                $this->report("Annullamento '{$description}' del {$date}: nessuna notifica da rimuovere.");

                return 0;
            }

            $notifications->delete();

            DB::table('invoices')                                                                   // fatture riportate allo stato iniziale
                ->whereIn('id', $invoiceIds)
                ->update([
                    'service_code' => null,
                    'sdi_code'     => null,
                    'sdi_status'   => $toStatus->value,
                    'sdi_info'     => null,
                    'sdi_date'     => null,
                ]);

            $count = $invoiceIds->count();

            $this->report("Annullamento '{$description}' del {$date}: {$count} fatture riportate a '{$toStatus->value}'.");

            return $count;
        });
    }

    /**
     * Scrive l'esito nel log applicativo e a video, per seguire la lavorazione durante il migrate.
     */
    private function report(string $message): void
    {
        Log::info($message);

        echo $message . PHP_EOL;
    }
};
