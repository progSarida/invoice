<?php

namespace App\Models;

use App\Models\DocType;
// use App\Enums\DocType;
use App\Enums\ClientType;
use App\Enums\NumerationType;
use Illuminate\Database\Eloquent\Model;

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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getNumber(){
        return $this->progressive." / 0".$this->description." / ".date('Y');
    }

    public function docType()
    {
        return $this->belongsTo(DocType::class);
    }
}
