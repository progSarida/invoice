<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassiveItem extends Model
{
    protected $fillable = [
        'company_id',
        'passive_invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'vat_rate'
    ];

    protected $casts = [
        //
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function passiveInvoice(){
        return $this->belongsTo(PassiveInvoice::class, 'passive_invoice_id');
    }
}
