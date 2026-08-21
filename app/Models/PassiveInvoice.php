<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PassiveInvoice extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'parent_id',
        'downloaded',
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
        'downloaded' => 'boolean',
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

    // Tutti i documenti figli (note di credito, note di debito, ...) senza filtro sul tipo documento
    public function children(){
        return $this->hasMany(PassiveInvoice::class, 'parent_id', 'id');
    }

    // Note di variazione (credito e debito) collegate al documento
    public function variationNotes(){
        return $this->hasMany(PassiveInvoice::class, 'parent_id', 'id')->whereIn('doc_type', ['TD04', 'TD05']);
    }

    public function postalExpenses(){
        return $this->hasMany(PostalExpense::class, 'passive_invoice_id', 'id');
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

    /**
     * Relazioni che impediscono l'eliminazione del documento, con le etichette
     * (singolare, plurale) usate nel messaggio mostrato all'utente.
     * Le voci (passiveItems) non sono incluse: sono parte del documento e vengono
     * eliminate in cascata dal database.
     */
    protected const DELETION_BLOCKING_RELATIONS = [
        'children' => ['documento collegato', 'documenti collegati'],
        'passivePayments' => ['pagamento registrato', 'pagamenti registrati'],
        'postalExpenses' => ['spesa postale collegata', 'spese postali collegate'],
    ];

    /** Cache per singola istanza dei collegamenti bloccanti già calcolati */
    protected ?array $deletionBlockers = null;

    /**
     * Elenco descrittivo dei collegamenti che bloccano l'eliminazione del documento.
     * Usa i conteggi già caricati (loadCount/withCount) se disponibili.
     */
    public function getDeletionBlockers(): array
    {
        // I callback del modale interrogano il record più volte per singolo render
        if (!is_null($this->deletionBlockers)) {
            return $this->deletionBlockers;
        }

        $blockers = [];

        foreach (static::DELETION_BLOCKING_RELATIONS as $relation => [$singular, $plural]) {
            $countAttribute = Str::snake($relation) . '_count';
            $count = (int) ($this->$countAttribute ?? $this->$relation()->count());

            if ($count > 0) {
                $blockers[] = $count . ' ' . ($count === 1 ? $singular : $plural);
            }
        }

        return $this->deletionBlockers = $blockers;
    }

    /**
     * Motivo per cui la fattura passiva non è eliminabile, oppure null se lo è.
     */
    public function getDeletionBlockReason(): ?string
    {
        if ($this->downloaded) {                                                        // origine SdI: condizione non rimuovibile
            return 'La fattura è stata scaricata dallo SdI: si possono eliminare solo le fatture passive inserite manualmente.';
        }

        if ($this->pi_validation_id) {
            return 'La fattura è validata: annulla prima la validazione per poterla eliminare.';
        }

        $blockers = $this->getDeletionBlockers();

        if (empty($blockers)) {
            return null;
        }

        $last = array_pop($blockers);
        $list = empty($blockers) ? $last : implode(', ', $blockers) . ' e ' . $last;

        return "Il documento non può essere eliminato perché è collegato a: {$list}."
            . ' Elimina prima gli elementi collegati.';
    }

    public function isDeletable(): bool
    {
        return is_null($this->getDeletionBlockReason());
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
            // Rete di sicurezza: fatture scaricate dallo SdI, validate o con elementi
            // collegati non possono essere eliminate, anche se la cancellazione arriva
            // da un punto diverso dal pannello.
            // Le voci non bloccano: le elimina il database in cascata.
            if (!$invoice->isDeletable()) {
                return false;
            }
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
     * Totale delle note di variazione collegate al documento: le note di debito
     * aumentano il dovuto, quindi vanno sottratte a quelle di credito.
     */
    public function getNotesTotal(): float
    {
        return $this->getCreditNotesTotal() - $this->getDebitNotesTotal();
    }

    /**
     * Somma delle sole note di credito (TD04) collegate al documento.
     */
    public function getCreditNotesTotal(): float
    {
        return (float) $this->creditNotes()->sum('total');
    }

    /**
     * Somma delle sole note di debito (TD05) collegate al documento.
     */
    public function getDebitNotesTotal(): float
    {
        return (float) $this->debitNotes()->sum('total');
    }

    /**
     * Residuo calcolato sulle note di variazione collegate: dovuto meno note e pagamenti.
     */
    public function getResidue(): float
    {
        return (float) $this->total - $this->getNotesTotal() - (float) $this->total_payment;
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
