<?php

namespace App\Models;

use App\Enums\FundType;
use App\Enums\VatCodeType;
use Illuminate\Database\Eloquent\Model;

class SocialContribution extends Model
{
    protected $fillable = [
        'name',
        'surname',
        'tax_code',
        'email',
        'pec'
    ];

    protected $casts = [
        'fund' => FundType::class,
        'vat_code' => VatCodeType::class
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
