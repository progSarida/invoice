<?php

namespace App\Models;

use App\Enums\SdiStatus;
use App\Enums\TransactionType;
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
        'transaction_type',
        'code',
        'start_date',
        'end_date',
        'quantity',
        'measure_unit',
        'unit_price',
        'amount',
        'total',
        'vat_code_type',
    ];

    protected $casts = [
        'vat_code_type' =>  VatCodeType::class,
        'transaction_type' => TransactionType::class,
        'start_date' => 'date',
        'end_date' => 'date',
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

    // Calcola il totale della voce della fattura
    public function calculateTotal(): void
    {
        $rate = $this->vat_code_type?->getRate() / 100 ?? 0;
        $this->total = $this->amount + ($this->amount * $rate);
    }

    // Verifica se ci sono le condizioni per inserire l'imposta di bollo (gli importi esenti IVA sono uguali o superiori al valore indicato) e nel caso lo fa
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
            } else {
                // Le condizioni NON sono soddisfatte: elimina l'eventuale voce di bollo
                InvoiceItem::where('invoice_id', $this->invoice->id)
                    ->where('vat_code_type', 'vc06a')
                    ->delete();
            }
        }
    }
}
