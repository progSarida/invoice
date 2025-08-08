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
        'auto',                              // campo per distiguere tra voci inserite dalll'operatore e voci ritenute, riepiloghi e cassa perv. inserite auttomaticamente
    ];

    protected $casts = [
        'vat_code_type' =>  VatCodeType::class,
        'transaction_type' => TransactionType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'auto' => 'boolean',
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
        // $this->total = $this->amount + ($this->amount * $rate);
        $this->total = $this->amount;
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

    // Crea automaticamente le voci della fattura riferiti a ritenute, riepiloghi e casse previdenziali
    public function autoInsert()
    {
        // Elimino tutti gli InvoiceItem auto-generati per questa fattura
        InvoiceItem::where('invoice_id', $this->invoice->id)
            ->where('auto', true)
            ->delete();

        // $vats = $this->vatResume();                                             // Creazione array con dati riepiloghi IVA
        // $funds = $this->getFundBreakdown();                                     // Creazione array con dati casse previdenziali
        // if(count($funds) > 0)
        //     $vats = $this->updateResume($vats, $funds);                         // Aggiorna l'array con dati riepiloghi IVA con i dati delle casse previdenziali
        // --------------------------------------------------------------------------------------------------------------------------------------------
        $vats = $this->invoice->vatResume();                                                                 // Creazione array con dati riepiloghi IVA
        $funds = array_filter($this->invoice->getFundBreakdown(), function ($fund) {                         // Creazione array con dati casse previdenziali
            return isset($fund['fund_code'], $fund['rate'], $fund['amount'], $fund['taxable_base']);
        });
        if (count($funds) > 0) {
            $vats = $this->invoice->updateResume($vats, $funds);                                             // Aggiorna l'array con dati riepiloghi IVA con i dati delle casse previdenziali
        }
        // $withholdings = array_filter($this->invoice->company->withholdings->toArray(), function ($item) {    // Creazione array con dati ritenute
        //     return in_array($item['withholding_type'], [WithholdingType::RT01, WithholdingType::RT02])
        //         && isset($item['tipo_ritenuta'], $item['importo_ritenuta'], $item['aliquota_ritenuta'], $item['causale_pagamento']);
        // });
        // dd($withholdings);
        // --------------------------------------------------------------------------------------------------------------------------------------------
        $this->insertFunds($funds);
        $this->insertResumes($vats);
        $this->insertWithholdings();
    }

    // Genera le voci dei riepiloghi IVA e li inserisce come voci della fattura
    public function insertResumes($vats)
    {
        // dd($vats);
        foreach($vats as $vat) {
            // dd($vat);
            $a = [
                'invoice_id' => $this->invoice->id,
                'invoice_element_id' => null,
                'description' => "Riepilogo IVA" . " - " . $vat['norm'] . " - " . (is_numeric($vat['%']) ? $vat['%'] . '%' : $vat['%']),
                'amount' => null,
                'total' => (float) $vat['vat'],
                'vat_code_type' => null,
                'auto' => true
            ];
            $item = InvoiceItem::create($a);
            $item->save();
        }
    }

    // Genera le voci delle casse previdenziali e le insrisce come voci della fattura
    public function insertFunds($funds)
    {
        // dd($funds);
        foreach($funds as $fund) {
            // dd($vat);
            $a = [
                'invoice_id' => $this->invoice->id,
                'invoice_element_id' => null,
                'description' => "Cassa  prev." . " - " . $fund['fund'],
                'amount' => null,
                'total' => (float) $fund['amount'],
                'vat_code_type' => null,
                'auto' => true
            ];
            $item = InvoiceItem::create($a);
            $item->save();
        }
    }

    // Genera le voci delle ritenute e le inserisce come voci della fattura
    public function insertWithholdings()
    {
        // dd($withholdings);
        $invoice = $this->invoice;
        $selectedIds = is_array($invoice->withholdings) ? $invoice->withholdings : [];
        $withholdings = $invoice->company->withholdings->filter(function ($item) use ($selectedIds) {
            return in_array($item->id, $selectedIds);
        });
        $accontoValues = [
            WithholdingType::RT01,                               // Ritenuta d'acconto (persone fisiche)
            WithholdingType::RT02,                               // Ritenuta d'acconto (persone giuridiche)
        ];
        $hasWithholdingTax = collect($withholdings)
            ->search(fn($withholding) => in_array($withholding->withholding_type, $accontoValues));
        $withholdingAmount = 0;
        if(count($withholdings) > 0 && $hasWithholdingTax !== false &&
            !in_array($invoice->client->subtype, [ \App\Enums\ClientSubtype::MAN, \App\Enums\ClientSubtype::WOMAN, ])){
                $taxable = $invoice->getTaxable();
                // $withholdingAmount = $taxable * ($invoice->company->withholdings[$hasWithholdingTax]->rate / 100);
                $withholdingAmount = -($taxable * ($invoice->company->withholdings[$hasWithholdingTax]->rate / 100));
            }
        foreach($withholdings as $withholding){
            // dd($withholding);
            $a = [
                'invoice_id' => $invoice->id,
                'invoice_element_id' => null,
                'description' => $withholding->withholding_type->getPrint(),
                'amount' => null,
                'total' => (float) $withholdingAmount,
                'vat_code_type' => null,
                'auto' => true
            ];
            $item = InvoiceItem::create($a);
            $item->save();
        }

    }
}
