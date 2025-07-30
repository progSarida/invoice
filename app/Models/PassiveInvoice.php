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
        'payment_term',
        'payment_method',
        'payment_deadline',
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
}
