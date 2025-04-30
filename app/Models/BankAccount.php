<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    //

    public function company(){
        $this->belongsTo(Company::class);
    }
}
