<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassiveDownload extends Model
{
    protected $fillable = [
        'company_id',
        'date',
        'success'
    ];

    protected $casts = [
        'date' => 'date',
        'success' => 'boolean'
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }
}
