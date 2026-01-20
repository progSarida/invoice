<?php

namespace App\Models;

use App\Enums\SdiRequestType;
use Illuminate\Database\Eloquent\Model;

class SdiRequest extends Model
{
    protected $fillable = [
        'company_id',
        'request_date',
        'sdi_request_type',
        'invoice_id',
    ];

    protected $casts = [
         'sdi_request_type' => SdiRequestType::class
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }
}
