<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        //
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function agencies(){
        return $this->hasMany(Agency::class);
    }
}
