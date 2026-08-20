<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PassiveInvoice extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'parent_id',
        'doc_type',
        'invoice_date',
        'number',
        'description',
        'total',
        'total_payment',
        'last_payment_date',
        'sdi_status',
        'sdi_code',
        'payment_mode',
        'payment_type',
        'payment_deadline',
        'bank',
        'iban',
        'user_id',
        'pi_validation_id',
        'pi_validation_date',
        'pi_validation_user_id',
        'filename',
        'xml_path',
        'pdf_path',
        'attachments_path'
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'pi_validation_date' => 'date',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class);
    }

    public function docType(){
        return $this->belongsTo(DocType::class, 'doc_type', 'name');
    }

    public function parent(){
        return $this->belongsTo(PassiveInvoice::class, 'parent_id', 'id');
    }

    public function creditNotes(){
        return $this->hasMany(PassiveInvoice::class, 'parent_id', 'id')->where('doc_type', 'TD04');
    }

    public function debitNotes(){
        return $this->hasMany(PassiveInvoice::class, 'parent_id', 'id')->where('doc_type', 'TD05');
    }

    public function passiveItems(){
        return $this->hasMany(PassiveItem::class,'passive_invoice_id','id');
    }

    public function passivePayments(){
        return $this->hasMany(PassivePayment::class);
    }

    public function piValidation(){
        return $this->belongsTo(PiValidation::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function piValidationUser(){
        return $this->belongsTo(User::class, 'pi_validation_user_id');
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            $invoice->total_payment = 0.00;
            $invoice->total_note = 0.00;
            $invoice->user_id = Auth::id();                                                 // registro l'utente che crea la fattura passiva
            if ($invoice->pi_validation_id) {                                               // fattura creata già in uno stato di validazione
                $invoice->pi_validation_date = today();
                $invoice->pi_validation_user_id = Auth::id();
            }
        });

        static::created(function ($invoice) {
            //
        });

        static::updating(function ($invoice) {
            if ($invoice->isDirty('pi_validation_id')) {                                    // registro chi e quando cambia lo stato di validazione
                $invoice->pi_validation_date = $invoice->pi_validation_id ? today() : null;
                $invoice->pi_validation_user_id = $invoice->pi_validation_id ? Auth::id() : null;
            }
        });

        static::saved(function ($invoice) {
            //
        });

        static::deleting(function ($invoice) {
            //
        });

    }

    /**
     * Scope per fatture passive con ritenuta d'acconto
     */
    public function scopeWithholdings($query)
    {
        return $query->whereHas('passiveItems', function ($q) {
            $q->where('description', 'like', '%Ritenuta persone%');
        });
    }

    /**
     * Scope per fatture passive senza ritenuta d'acconto
     */
    public function scopeWithoutWithholdings($query)
    {
        return $query->whereDoesntHave('passiveItems', function ($q) {
            $q->where('description', 'like', '%Ritenuta persone%');
        });
    }

    /**
     * Tolleranza sul residuo configurata per l'azienda di sessione.
     * Fuori dal contesto del tenant (code, comandi artisan, export) non c'è
     * azienda di sessione e torna 0, quindi gli scope restano espliciti e
     * si comportano come se la tolleranza non fosse impostata.
     */
    public static function paymentTolerance(): float
    {
        return (float) (Filament::getTenant()?->passive_payment_tolerance ?? 0);
    }

    /**
     * Tipi documento effettivamente presenti nelle fatture passive dell'azienda di
     * sessione, come opzioni codice => descrizione, ordinati per numero di fatture.
     *
     * @param  string  $direction  'desc' per i tipi più usati in cima, 'asc' per i meno usati
     * @return array<string, ?string>
     */
    public static function docTypeOptions(string $direction = 'desc'): array
    {
        return self::select('passive_invoices.doc_type', 'doc_types.description')
            ->selectRaw('COUNT(passive_invoices.id) as invoices_count')
            ->leftJoin('doc_types', 'passive_invoices.doc_type', '=', 'doc_types.name')
            ->where('passive_invoices.company_id', Filament::getTenant()?->id)
            ->groupBy('passive_invoices.doc_type', 'doc_types.description')
            ->orderBy('invoices_count', $direction)
            ->get()
            ->pluck('description', 'doc_type')
            ->toArray();
    }

    /**
     * Residuo tra il dovuto e quanto già coperto da pagamenti e note di credito.
     */
    public function getResidualAttribute(): float
    {
        return (float) $this->total - ((float) $this->total_payment + (float) $this->total_note);
    }

    /**
     * La fattura è coperta, tenendo conto della tolleranza sul residuo.
     * La tolleranza si applica solo se sulla fattura risultano pagamenti.
     */
    public function isPaid(?float $tolerance = null): bool
    {
        $tolerance = ((float) $this->total_payment) != 0.00 ? (float) ($tolerance ?? 0) : 0.00;

        return $this->residual <= $tolerance;
    }

    /**
     * Scope per fatture passive coperte da pagamenti e note di credito.
     * La tolleranza sul residuo si applica solo alle fatture con pagamenti:
     * una fattura senza pagamenti non è mai considerata pagata per tolleranza.
     */
    public function scopePaid($query, ?float $tolerance = null)
    {
        $tolerance = (float) ($tolerance ?? 0);

        return $query->whereRaw(
            '(total_payment + total_note) >= total - (CASE WHEN total_payment != 0.00 THEN ? ELSE 0 END)',
            [$tolerance]
        );
    }

    /**
     * Scope per fatture passive non coperte: è l'esatto complemento di scopePaid().
     */
    public function scopeUnpaid($query, ?float $tolerance = null)
    {
        $tolerance = (float) ($tolerance ?? 0);

        return $query->whereRaw(
            '(total_payment + total_note) < total - (CASE WHEN total_payment != 0.00 THEN ? ELSE 0 END)',
            [$tolerance]
        );
    }

    /**
     * Scope per fatture passive pagate solo in parte: hanno pagamenti,
     * ma il residuo supera la tolleranza.
     */
    public function scopePartiallyPaid($query, ?float $tolerance = null)
    {
        return $query->unpaid($tolerance)->whereRaw('total_payment != 0.00');
    }

    /**
     * Scope per fatture passive su cui non è stato imputato nulla.
     * Non è influenzato dalla tolleranza, perché senza pagamenti non si applica.
     */
    public function scopeNotPaid($query)
    {
        return $query->whereRaw('(total_payment + total_note) = 0.00');
    }

    // Aggiorna il totale della fattura poassiva
    public function updateTotal(): void
    {
        Log::info('Aggiornamento totale dovuto__________________________________________________________________________________________');
        $totals = $this->passiveItems()->sum('total_price');
        Log::info('Totale dovuto con IVA: ' . $totals);
        $this->total = $totals;
        $this->save();
    }

    // Aggiorna il totale delle note di credito della fattura parent
    public function updateParentNotes(): void
    {
        Log::info('Aggiornamento totale note di credito parent___________________________________________________________________________');
        $totalNote = $this->total;
        Log::info('Totale nota: ' . $totalNote);
        if($this->parent->total_note)
            $this->parent->total_note += $totalNote;
        else
            $this->parent->total_note = $totalNote;
        $this->save();
    }
}
