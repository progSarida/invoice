<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\ClientSubType;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    //
    protected $fillable = [
        'client_id',
        'type',
        'subtype',
        'denomination',
        'address',
        'zip_code',
        'tax_code',
        'vat_code',
        'city_id',
        'email',
        'ipa_code',
    ];

    protected $casts = [
        'type' => ClientType::class,
        'subtype' => ClientSubType::class
    ];


    public function city(){
        return $this->belongsTo(City::class);
    }

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function tenders(){
        return $this->hasMany(Tender::class);
    }

    public function contracts(){
        return $this->hasMany(Contract::class);
    }
}
