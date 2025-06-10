<?php

namespace App\Models;

use App\Enums\ContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDetail extends Model
{
    protected $fillable = [
        'contract_id',
        'number',
        'contract_type',
        'date',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'contract_type' => ContractType::class
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(NewContract::class, 'contract_id');
    }
}
