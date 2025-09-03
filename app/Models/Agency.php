<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    protected $fillable = [
        'insurance_id',
        'name',
        'description',
    ];

    protected $casts = [
        //
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function insurance(){
        return $this->belongsTo(Insurance::class);
    }
}
