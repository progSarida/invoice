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
        'state_id',
        'address',
        'zip_code',
        'city_id',
        'city',
        'birth_date',
        'birth_place',
        'tax_code',
        'vat_code',
        'phone',
        'email',
        'pec',
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

    public function isPublic(){
        return $this->type === 'public';
    }

    public function isPrivate(){
        return $this->type === 'private';
    }
}
