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
        'is_active'
    ];

    public function users(){
        return $this->belongsToMany(User::class);
    }

    public function bankAccounts(){
        return $this->hasMany(BankAccount::class);
    }
}
