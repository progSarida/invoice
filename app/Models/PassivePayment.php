<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassivePayment extends Model
{
    protected $fillable = [
        'companny_id',
        'passive_invoice_id',
        'amount',
        'payment_date',
        'registration_date',
        'registration_user_id',
        'validated',
        'validation_date',
        'validation_user_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'validated' => 'boolean',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function passiveInvoice(){
        return $this->belongsTo(PassiveInvoice::class, 'passive_invoice_id');
    }
}
