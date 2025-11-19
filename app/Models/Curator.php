<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curator extends Model
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
