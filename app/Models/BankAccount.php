<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    //

    protected $fillable = [
        'name',
        'iban',
        'bic',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }
}
