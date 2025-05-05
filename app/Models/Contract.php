<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'client_id',
        'tax_type',
        'type',
        'number',
        'validity_date',
        'contract_date',
    ];
    
    protected $casts = [
        'tax_type' =>  TaxType::class,
        'type' => ContractType::class
    ];
    //

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }
}
