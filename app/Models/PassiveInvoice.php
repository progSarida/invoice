<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassiveInvoice extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'doc_type',
        'invoice_date',
        'number',
        'description',
        'total',
        'sdi_status',
        'sdi_code',
        'payment_mode',
        'payment_type',
        'payment_deadline',
        'bank',
        'iban',
        'filename',
        'xml_path',
        'pdf_path'
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

    public function passiveItems(){
        return $this->hasMany(PassiveItem::class,'passive_invoice_id','id');
    }
}
