<?php

namespace App\Models;

use App\Enums\PaymentReasonType;
use App\Enums\WithholdingType;
use Illuminate\Database\Eloquent\Model;

class Withholding extends Model
{
    protected $fillable = [
        'withholding_type',
        'rate',
        'taxable_perc ',
        'payment_reason'
    ];

    protected $casts = [
        'withholding_type' => WithholdingType::class,
        'payment_reason' => PaymentReasonType::class
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
