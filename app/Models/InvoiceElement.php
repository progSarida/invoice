<?php

namespace App\Models;

use App\Enums\VatCodeType;
use Illuminate\Database\Eloquent\Model;

class InvoiceElement extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'amount',
        'vat_code_type',
    ];

    protected $casts = [
        'vat_code_type' => VatCodeType::class,
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

}
