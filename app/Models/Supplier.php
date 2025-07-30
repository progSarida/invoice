<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'denomination',
        'tax_code',
        'vat_code',
        'address',
        'civic_number',
        'zip_code',
        'city',
        'province',
        'country',
        'rea_office',
        'rea_number',
        'capital',
        'sole_share',
        'liquidation_status',
        'phone',
        'fax',
        'email',
        'pec',
        'bank',
        'iban',
        'bic',
        'swift'
    ];

    protected $casts = [
        //
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }
}
