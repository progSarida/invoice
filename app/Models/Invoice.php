<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    //
    protected $fillable = [
        'name'
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }
}
