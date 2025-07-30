<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    protected $fillable = [
        'company_id',
        'description',
        'note',
        'date',
        'dispatched'
    ];

    protected $casts = [
        'date' => 'date',
        'dispatched' => 'boolean',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }
}
