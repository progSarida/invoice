<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Productor extends Model
{
    protected $fillable = [
        'name',
        'surname',
        'tax_code',
        'email',
        'pec'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
