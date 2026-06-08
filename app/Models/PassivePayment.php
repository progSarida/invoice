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
        'payment_type',
        'registration_date',
        'registration_user_id',
        'validated',
        'validation_date',
        'validation_user_id',
        'note'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'validated' => 'boolean',
        'validation_date' => 'date',
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
            $updatedInvoice = false;
            $invoice = $payment->passiveInvoice;

            if ($payment->isDirty('amount') && $invoice) {
                $originalAmount = $payment->getOriginal('amount');
                $invoice->total_payment = $invoice->total_payment - $originalAmount + $payment->amount;
                $updatedInvoice = true;
            }

            if ($payment->isDirty('payment_date') && $invoice && $invoice->last_payment_date < $payment->payment_date) {
                $invoice->last_payment_date = $payment->payment_date;
                $updatedInvoice = true;
            }

            if($updatedInvoice) { $invoice->save(); }

            if(!$payment->validated) {
                $payment->validation_date = null;
                $payment->validation_user_id = null;
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
