<?php

namespace App\Models;

use App\Enums\SdiStatus;
use App\Enums\VatCodeType;
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
}
