<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class ActivePayments extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'bank_account_id',
        'registration_date',
        'registration_user_id',
        'validated',
        'validation_date',
        'validation_user_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'registration_date' => 'date',
        'validation_date' => 'date',
        'accepted' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->with(['sectional', 'client']);
    }

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(){
        return $this->belongsTo(BankAccount::class);
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

    protected static function booted()
    {
        static::creating(function ($payment) {
            $payment->company_id = Filament::getTenant()?->id;
            $payment->registration_date = now()->toDateString();
            $payment->registration_user_id = Auth::id();
        });

        static::created(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->total_payment += $payment->amount;
                // $payment->invoice->last_payment_date = $payment->payment_date;
                if ( is_null($payment->invoice->last_payment_date) || $payment->invoice->last_payment_date < $payment->payment_date ) {
                    $payment->invoice->last_payment_date = $payment->payment_date;
                }
                $payment->invoice->save();
            }
        });

        static::updating(function ($payment) {
            $payment->registration_date = now()->toDateString();
            $payment->registration_user_id = Auth::id();

            if ($payment->isDirty('amount') && $payment->invoice) {
                $originalAmount = $payment->getOriginal('amount');
                $invoice = $payment->invoice;
                $invoice->total_payment = $invoice->total_payment - $originalAmount + $payment->amount;
                $invoice->save();
            }
        });

        static::deleting(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->total_payment -= $payment->amount;
                $payment->invoice->save();
            }
        });

    }
}
