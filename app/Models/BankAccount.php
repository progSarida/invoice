<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    //

    protected $fillable = [
        'name',
        'holder',
        'number',
        'iban',
        'bic',
        'swift',
        'agency',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function invoices(){
        return $this->hasMany(Invoice::class);
    }
}
