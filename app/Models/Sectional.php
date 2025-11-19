<?php

namespace App\Models;

// use App\Enums\DocType;
use App\Enums\ClientType;
use App\Enums\NumerationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sectional extends Model
{
    protected $fillable = [
        'company_id',
        'description',
        'client_type',
        // 'doc_type',
        'doc_type_id',
        'numeration_type',
        'progressive'
    ];

    protected $casts = [
        'client_type' => ClientType::class,
        // 'doc_type' => DocType::class,
        'numeration_type' => NumerationType::class
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function getNumber(){
        return $this->progressive." / 0".$this->description." / ".date('Y');
    }

    // public function docType(): BelongsTo
    // {
    //     return $this->belongsTo(DocType::class, 'doc_type_id');
    // }

    public function docTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocType::class, 'doc_type_sectional', 'sectional_id', 'doc_type_id');
    }
}
