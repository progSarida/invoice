<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StampDuty extends Model
{
    protected $fillable = [
        'active',
        'value',
        'virtual_stamp',
        'virtual_amount',
        'add_row',
        'row_description',
        'amount'
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
