<?php

namespace App\Models;

use App\Enums\TaxType;
use App\Enums\TenderPaymentType;
use Illuminate\Database\Eloquent\Model;

class Tender extends Model
{
    //
    protected $fillable = [
        'client_id',
        'tax_type',
        'type',
        'office_name',
        'office_code',
        'date',
        'cig_code',
        'cup_code',
        'rdo_code',
        'reference_code',
    ];

    protected $casts = [
        'tax_type' =>  TaxType::class,
        'type' => TenderPaymentType::class
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }

    public function invoices(){
        return $this->hasMany(Invoice::class);
    }

    public function container(){
        return $this->belongsTo(Container::class);
    }
}
