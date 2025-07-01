<?php

namespace App\Models;

use App\Enums\SdiStatus;
use App\Enums\VatCodeType;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    //

    protected $fillable = [
        'invoice_id',
        'invoice_element_id',
        'description',
        'amount',
        'total',
        'vat_code_type',
    ];

    protected $casts = [
        'vat_code_type' =>  VatCodeType::class,
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceElement()
    {
        return $this->belongsTo(InvoiceElement::class, 'invoice_element_id', 'id');
    }

    protected static function booted()
    {
        static::saved(function ($item) {
            $item->invoice?->updateTotal();

            if ($item->invoice?->parent_id) {
                $item->invoice->invoice?->updateTotalNotes();
            }
        });

        static::deleted(function ($item) {
            $item->invoice?->updateTotal();

            if ($item->invoice?->parent_id) {
                $item->invoice->invoice?->updateTotalNotes();
            }
        });
    }

    public function calculateTotal(): void
    {
        $rate = $this->vat_code_type?->getRate() / 100 ?? 0;
        $this->total = $this->amount + ($this->amount * $rate);
    }

    public function checkStampDuty()
    {
        $stampDuty = $this->invoice->company->stampDuty;
        if($stampDuty->active){
            $vats = $this->invoice->vatResume();
            $free = 0;
            $insert = true;
            foreach($vats as $key => $vat){
                if($key == 'vc06a') $insert = false;
                if($vat['free']) $free += $vat['taxable'];
            }
            if($insert && $free >= $stampDuty->value){
                $a = [
                    'invoice_id' => $this->invoice->id,
                    'invoice_element_id' => null,
                    'description' => $stampDuty->row_description,
                    'amount' => (string) $stampDuty->amount,
                    'vat_code_type' => 'vc06a'
                ];
                $item = InvoiceItem::create($a);
                $item->calculateTotal();
                $item->save();
                return $item;
            }
        }
    }
}
