<?php

namespace App\Models;

use App\Enums\TaxRegimeType;
use App\Enums\VatEnforceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalProfile extends Model
{
    protected $fillable = [
        'tax_regime',
        'vat_enforce',
        'vat_enforce_type'
    ];

    protected $casts = [
        'tax_regime' =>  TaxRegimeType::class,
        'vat_enforce_type' =>  VatEnforceType::class
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
