<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\DocType;
use App\Enums\NumerationType;
use Illuminate\Database\Eloquent\Model;

class Sectional extends Model
{
    protected $fillable = [
        'description',
        'client_type',
        'doc_type',
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
}
