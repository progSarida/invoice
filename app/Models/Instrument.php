<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instrument extends Model
{
    protected $fillable = [
        'name',
        'description',
        'order'
    ];

    protected $casts = [
        //
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
