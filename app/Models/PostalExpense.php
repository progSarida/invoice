<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalExpense extends Model
{
    protected $fillable = [
        //
    ];

    protected $casts = [
        //
    ];

    // public function shipmentType(){
    //     return $this->belongsTo(ShipmentType::class);                            // in sospeso
    // }

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function newContract(){
        return $this->belongsTo(NewContract::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class);
    }

    public function shipmentInsertUser(){
        return $this->belongsTo(User::class, 'shipment_insert_user_id');
    }

    public function notifyInsertUser(){
        return $this->belongsTo(User::class, 'notify_insert_user_id');
    }

    public function paymentInsertUser(){
        return $this->belongsTo(User::class, 'payment_insert_user_id');
    }

    public function reinvoiceInsertUser(){
        return $this->belongsTo(User::class, 'reinvoice_insert_user_id');
    }

    public function notifyDateRegistrationUser(){
        return $this->belongsTo(User::class, 'notify_date_registration_user_id');
    }
}
