<?php

namespace App\Models;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class PassivePayment extends Model
{
    protected $fillable = [
        'company_id',
        'passive_invoice_id',
        'amount',
        'payment_date',
        'bank',
        'iban',
        'bank_account_id',
        'registration_date',
        'registration_user_id',
        'validated',
        'validation_date',
        'validation_user_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'validated' => 'boolean',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function passiveInvoice(){
        return $this->belongsTo(PassiveInvoice::class, 'passive_invoice_id');
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

    protected static function booted()
    {
        static::creating(function ($payment) {
            $payment->company_id = Filament::getTenant()?->id;
            $payment->registration_date = now()->toDateString();
            $payment->registration_user_id = Auth::id();
        });

        static::created(function ($payment) {
            if ($payment->passiveInvoice) {
                $payment->passiveInvoice->total_payment += $payment->amount;
                // $payment->passiveInvoice->last_payment_date = $payment->payment_date;
                if ( is_null($payment->passiveInvoice->last_payment_date) || $payment->passiveInvoice->last_payment_date < $payment->payment_date ) {
                    $payment->passiveInvoice->last_payment_date = $payment->payment_date;
                }
                $payment->passiveInvoice->save();
            }
        });

        static::updating(function ($payment) {
            $payment->registration_date = now()->toDateString();
            $payment->registration_user_id = Auth::id();

            if ($payment->isDirty('amount') && $payment->passiveInvoice) {
                $originalAmount = $payment->getOriginal('amount');
                $invoice = $payment->passiveInvoice;
                $invoice->total_payment = $invoice->total_payment - $originalAmount + $payment->amount;
                $invoice->save();
            }
        });

        static::deleting(function ($payment) {
            if ($payment->passiveInvoice) {
                $payment->passiveInvoice->total_payment -= $payment->amount;
                $payment->passiveInvoice->save();
            }
        });

    }
}
