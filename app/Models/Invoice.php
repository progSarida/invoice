<?php

namespace App\Models;

use App\Enums\TaxType;
use App\Enums\SdiStatus;
// use App\Enums\AccrualType;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\AccrualType;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    //
    protected $fillable = [
        'name'
    ];

    protected $casts = [
        'tax_type' =>  TaxType::class,
        'invoice_type' => InvoiceType::class,
        // 'accrual_type' => AccrualType::class,
        'payment_status' => PaymentStatus::class,
        'payment_type' => PaymentType::class,
        'sdi_status' => SdiStatus::class
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }

    public function bankAccount(){
        return $this->belongsTo(BankAccount::class);
    }

    public function invoice_items(){
        return $this->hasMany(InvoiceItem::class);
    }

    public function sdi_notifications(){
        return $this->hasMany(SdiNotification::class);
    }

    public function tender(){
        return $this->belongsTo(Tender::class);
    }

    public function invoice(){
        return $this->belongsTo(Invoice::class,'parent_id');
    }

    public function credit_notes(){
        return $this->hasMany(Invoice::class,'id','parent_id');
    }

    public function getInvoiceNumber(){
        $number = "";
        for($i=strlen($this->number);$i<3;$i++)
        {
            $number.= "0";
        }
        $number = $number.$this->number;
        return $number." / 0".$this->section." / ".$this->year;
    }

    public function accrualType(){
        return $this->belongsTo(AccrualType::class,'accrual_type_id');
    }

    public function manageType(){
        return $this->belongsTo(ManageType::class,'manage_type_id');
    }
}
