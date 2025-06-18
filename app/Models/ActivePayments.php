<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Filament\Facades\Filament;

class ActivePayments extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'registration_date',
        'registration_user_id',
        'validated',
        'validation_date',
        'validation_user_id',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'registration_date' => 'datetime',
        'validation_date' => 'datetime',
        'accepted' => 'boolean',
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function registrationUser(){
        return $this->belongsTo(User::class, 'registration_user_id');
    }

    public function validationUser(){
        return $this->belongsTo(User::class, 'validation_user_id');
    }

    // public function scopeOldActivePayments($query)
    // {
    //     return $query->whereNull('registration_user_id');
    // }

    // public function scopeNewActivePayments($query)
    // {
    //     return $query->whereNotNull('registration_user_id');
    // }

    public function scopeOldActivePayments($query)
    {
        $tenant = Filament::getTenant();
        return $query->whereNull('registration_user_id')
                     ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id));
    }

    public function scopeNewActivePayments($query)
    {
        $tenant = Filament::getTenant();
        return $query->whereNotNull('registration_user_id')
                     ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id));
    }
}
