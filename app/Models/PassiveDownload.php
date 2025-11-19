<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassiveDownload extends Model
{
    protected $fillable = [
        'company_id',
        'date',
        'new_suppliers',
        'new_invoices'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }
}
