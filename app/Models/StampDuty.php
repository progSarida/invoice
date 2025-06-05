<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StampDuty extends Model
{
    protected $fillable = [
        'active',
        'value',
        'add_row',
        'row_description'
    ];

    protected $casts = [
        'active' => 'boolean',
        'add_row' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
