<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassiveItem extends Model
{
    protected $fillable = [
        'passive_invoice_id',
        'description',
        'transaction_type',
        'start_date',
        'end_date',
        'code',
        'quantity',
        'measure_unit',
        'unit_price',
        'total_price',
        'vat_rate'
    ];

    protected $casts = [
        //
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function passiveInvoice(){
        return $this->belongsTo(PassiveInvoice::class, 'passive_invoice_id');
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            //
        });

        static::created(function ($item) {
            //
        });

        static::updating(function ($item) {
            //
        });

        static::saved(function ($item) {
            $item->passiveInvoice?->updateTotal();
            if($item->passiveInvoice->parent){
                $item->passiveInvoice->updateParentNotes();
            }
        });

        static::deleting(function ($item) {
            //
        });

        static::deleted(function ($item) {
            $item->passiveInvoice?->updateTotal();
            if($item->passiveInvoice->parent){
                $item->passiveInvoice->updateParentNotes();
            }
        });

    }
}
