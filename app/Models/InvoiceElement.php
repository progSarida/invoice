<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Enums\VatCodeType;
use Illuminate\Database\Eloquent\Model;

class InvoiceElement extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'transaction_type',
        'code',
        'quantity',
        'measure_unit',
        'unit_price',
        'amount',
        'vat_code_type',
    ];

    protected $casts = [
        'transaction_type' => TransactionType::class,
        'vat_code_type' => VatCodeType::class,
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

}
