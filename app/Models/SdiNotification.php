<?php

namespace App\Models;

use App\Enums\SdiStatus;
use Illuminate\Database\Eloquent\Model;

class SdiNotification extends Model
{
    //

    protected $fillable = [
        'invoice_id',
        'code',
        'status',
        'date',
        'description',
    ];
    
    protected $casts = [
         'status' => SdiStatus::class
    ];

    protected const INVOICE_FIELDS = [                                       // campo della notifica => campo corrispondente della fattura
        'status' => 'sdi_status',
        'code' => 'sdi_code',
        'date' => 'sdi_date',
        'description' => 'sdi_info',
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted()
    {
        static::creating(function ($notification) {
            //
        });

        static::created(function ($notification) {
            if (! $notification->isLastNotification()) {                      // Esiste già una notifica più recente: non riporto indietro la fattura
                return;
            }

            $notification->syncInvoice($notification->invoiceUpdates());      // Allineo i dati SDI della fattura collegata a quelli della notifica
        });

        static::updating(function ($notification) {
            //
        });

        static::updated(function ($notification) {
            if (! $notification->isLastNotification()) {                      // Comanda sempre la notifica più recente
                return;
            }

            $changed = array_intersect(                                       // Propago solo i campi realmente modificati: correggere la descrizione
                array_keys(self::INVOICE_FIELDS),                             // non deve trascinare anche lo stato, che sulla fattura può essere custom
                array_keys($notification->getChanges())
            );

            $notification->syncInvoice($notification->invoiceUpdates($changed));
        });

        static::saved(function ($notification) {
            //
        });

        static::deleting(function ($notification) {
            //
        });

        static::deleted(function ($notification) {
            $mirrored = $notification->mirroredFields();                      // Campi in cui la fattura rispecchiava la notifica cancellata

            if (empty($mirrored)) {                                           // La fattura ha valori suoi (es. stato custom): non la tocco
                return;
            }

            $last = self::lastFor($notification->invoice_id);                 // Riallineo quei campi alla notifica rimasta più recente

            if (! $last) {                                                    // Nessuna notifica rimasta: lascio i dati SDI della fattura come sono
                return;
            }

            $last->syncInvoice($last->invoiceUpdates($mirrored));
        });

    }

    protected static function lastFor($invoiceId): ?self                      // Notifica più recente della fattura: a parità di data vince l'ultima inserita
    {
        if (! $invoiceId) {
            return null;
        }

        return self::query()
            ->where('invoice_id', $invoiceId)
            ->orderByRaw('date IS NULL')                                      // le notifiche senza data valgono come le più vecchie
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    protected function isLastNotification(): bool
    {
        return self::lastFor($this->invoice_id)?->getKey() == $this->getKey();
    }

    protected function invoiceUpdates(?array $only = null): array             // Campi da scrivere sulla fattura: solo quelli valorizzati sulla notifica
    {                                                                         // e diversi da quelli della fattura, così non si cancella mai un dato esistente
        $invoice = $this->invoice;

        if (! $invoice) {
            return [];
        }

        $data = [];

        foreach (self::INVOICE_FIELDS as $field => $invoiceField) {
            if ($only !== null && ! in_array($field, $only, true)) {
                continue;
            }

            if (blank($this->{$field}) || ! $this->isDifferent($invoice->{$invoiceField}, $this->{$field})) {
                continue;
            }

            $data[$invoiceField] = $this->{$field};
        }

        return $data;
    }

    protected function mirroredFields(): array                                // Campi della fattura che riportano esattamente il valore di questa notifica
    {
        $invoice = $this->invoice;

        if (! $invoice) {
            return [];
        }

        $fields = [];

        foreach (self::INVOICE_FIELDS as $field => $invoiceField) {
            if (filled($this->{$field}) && ! $this->isDifferent($invoice->{$invoiceField}, $this->{$field})) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    protected function isDifferent($invoiceValue, $value): bool
    {
        if ($value instanceof SdiStatus) {
            return $invoiceValue !== $value;
        }

        return (string) $invoiceValue !== (string) $value;
    }

    protected function syncInvoice(array $data): void
    {
        if (empty($data)) {                                                   // Fattura già allineata (es. aggiornata dal servizio SOAP prima della create):
            return;                                                           // evito una update inutile e gli hook di Invoice che ne conseguono
        }

        $this->invoice?->update($data);
    }
}
