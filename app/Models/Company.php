<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    //
    protected $fillable = [
        'name',
        'vat_number',
        'address',
        'city_code',
        'is_active',
        'email',
        'phone',
        'fax',
        'tax_number',
        'register',
        'register_province_id',
        'register_number',
        'register_date',
        'rea_province_id',
        'rea_number',
        'nominal_capital',
        'shareholders',
        'liquidation'
    ];

    public function users(){
        return $this->belongsToMany(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_code', 'code');
    }

    public function bankAccounts(){
        return $this->hasMany(BankAccount::class);
    }

    public function invoices(){
        return $this->hasMany(Invoice::class);
    }

    public function registerProvince(){
        return $this->belongsTo(Province::class, 'register_province_id');
    }

    public function reaProvince(){
        return $this->belongsTo(Province::class, 'rea_province_id');
    }
}
