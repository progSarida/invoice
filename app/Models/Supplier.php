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
        'notify_expense',
        'auto_payment',
        'card_expiration_date',
    ];

    protected $casts = [
        'notify_expense' => 'boolean',
        'auto_payment' => 'boolean',
        'card_expiration_date' => 'string',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    protected static function booted()
    {
        static::saving(function ($supplier) {
            // 1. Pulizia vat_code
            $vat = (string) $supplier->vat_code;
            if (strlen($vat) === 13) {
                $supplier->vat_code = substr($vat, 2);
            }
            // 2. Pulizia tax_code
            $tax = (string) $supplier->tax_code;
            if (strlen($tax) === 13) {
                $supplier->tax_code = substr($tax, 2);
            }
        });

        static::deleted(function ($supplier) {
            //
        });
    }
}
