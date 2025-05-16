<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\TaxType;
use App\Enums\InvoiceType;
use App\Enums\SdiStatus;

class Invoice extends Model
{
    //
    protected $fillable = [
        'name'
    ];

    protected $casts = [
        'tax_type' =>  TaxType::class,
        'invoice_type' => InvoiceType::class,
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
}
