<?php

namespace App\Models;

use App\Enums\SdiStatus;
use App\Enums\TransactionType;
use App\Enums\VatCodeType;
use App\Enums\WithholdingType;
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
        // 'auto',                              // campo per distiguere tra voci inserite dalll'operatore e voci ritenute, riepiloghi e cassa perv. inserite auttomaticamente
    ];

    protected $casts = [
        'vat_code_type' =>  VatCodeType::class,
        'transaction_type' => TransactionType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        // 'auto' => 'boolean',                    
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

    // 
    public function autoInsert()
    {
        $vats = $this->vatResume();                                   // Creazione array con dati riepiloghi IVA
        $funds = $this->getFundBreakdown();                           // Creazione array con dati casse previdenziali
        if(count($funds) > 0)
            $vats = $this->updateResume($vats, $funds);               // Aggiorna l'array con dati riepiloghi IVA con i dati delle casse previdenziali
        // --------------------------------------------------------------------------------------------------------------------------------------------
        $vats = $this->vatResume();
        $funds = array_filter($this->getFundBreakdown(), function ($fund) {
            return isset($fund['fund_code'], $fund['rate'], $fund['amount'], $fund['taxable_base']);
        });
        if (count($funds) > 0) {
            $vats = $this->updateResume($vats, $funds);
        }
        $withholdings = array_filter($this->company->withholdings->toArray(), function ($item) {
            return in_array($item['withholding_type'], [WithholdingType::RT01, WithholdingType::RT02])
                && isset($item['tipo_ritenuta'], $item['importo_ritenuta'], $item['aliquota_ritenuta'], $item['causale_pagamento']);
        });
        // --------------------------------------------------------------------------------------------------------------------------------------------
        $this->insertResumes($vats);
        $this->insertFunds($funds);
        $this->insertWithholdings();
    }

    // Genera le voci dei riepiloghi IVA e li inserisce come voci della fattura
    public function insertResumes($vats)
    {
        //
        
    }

    // Genera le voci delle casse previdenziali e le insrisce come voci della fattura
    public function insertFunds($funds)
    {
        // 
    }

    // Genera le voci delle ritenute e le inserisce come voci della fattura
    public function insertWithholdings()
    {
        // 
    }
}
