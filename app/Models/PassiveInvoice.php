<?php

namespace App\Models;

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
        'pi_validation_id',
        'filename',
        'xml_path',
        'pdf_path',
        'attachments_path'
    ];

    protected $casts = [
        'invoice_date' => 'date',
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

    protected static function booted()
    {
        static::creating(function ($invoice) {
            $invoice->total_payment = 0.00;
            $invoice->total_note = 0.00;
        });

        static::created(function ($invoice) {
            //
        });

        static::updating(function ($invoice) {
            //
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
