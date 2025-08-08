<?php

namespace App\Models;

use App\Enums\TaxType;
use App\Enums\SdiStatus;
// use App\Enums\AccrualType;
use App\Enums\TimingType;
use App\Enums\InvoiceType;
use App\Enums\PaymentMode;
use App\Enums\PaymentType;
use App\Enums\VatCodeType;
use App\Models\AccrualType;
use App\Enums\PaymentStatus;
use App\Enums\VatEnforceType;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    //
    protected $fillable = [
        'company_id',
        'client_id',
        'container_id',
        'contract_id',
        'parent_id',
        'tax_type',
        'invoice_type',
        'art_73',
        'social_contributions',
        'withholdings',
        'vat_enforce_type',
        'timing_type',
        'delivery_note',
        'delivery_date',
        'doc_type_id',
        'year_limit',
        'limit_motivation_type_id',
        'number',
        'section',
        'sectional_id',
        'year',
        'invoice_date',
        'budget_year',
        'accrual_year',
        'accrual_type_id',
        'manage_type_id',
        'description',
        'free_description',
        'vat',
        'bank_account_id',
        'payment_status',
        'payment_mode',
        'rate_number',
        'payment_type',
        'payment_days',
        'service_code',
        'sdi_status',
        'sdi_code',
        'sdi_date'
    ];

    protected $casts = [
        'tax_type' =>  TaxType::class,
        'invoice_type' => InvoiceType::class,
        'social_contributions' => 'array',
        'withholdings' => 'array',
        'vat_enforce_type' => VatEnforceType::class,
        // 'accrual_type' => AccrualType::class,
        'invoice_date' => 'date',
        'payment_status' => PaymentStatus::class,
        'payment_type' => PaymentType::class,
        'last_payment_date' => 'date',
        'payment_mode' => PaymentMode::class,
        'sdi_status' => SdiStatus::class,
        'timing_type' => TimingType::class
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

    public function invoiceItems(){
        return $this->hasMany(InvoiceItem::class,'invoice_id','id');
    }

    public function sdiNotifications(){
        return $this->hasMany(SdiNotification::class);
    }

    public function lastSdiNotification()
    {
        return $this->hasOne(SdiNotification::class)->latestOfMany('date');
    }

    public function activePayments(){
        return $this->hasMany(ActivePayments::class);
    }

    public function tender(){
        return $this->belongsTo(Tender::class);
    }

    public function invoice(){
        return $this->belongsTo(Invoice::class,'parent_id');
    }

    public function docType(){
        return $this->belongsTo(DocType::class,'doc_type_id');
    }

    public function accrualType(){
        return $this->belongsTo(AccrualType::class,'accrual_type_id');
    }

    public function manageType(){
        return $this->belongsTo(ManageType::class,'manage_type_id');
    }

    public function sectional(){
        return $this->belongsTo(Sectional::class,'sectional_id');
    }

    public function contract(){
        return $this->belongsTo(NewContract::class,'contract_id');
    }

    public function creditNotes(){
        return $this->hasMany(Invoice::class, 'parent_id', 'id');
    }

    public function contractDetail(){
        return $this->belongsTo(ContractDetail::class,'contract_detail_id');
    }

    // Ritorna i dettagli contratto cui fa riferimenti la fattura (obsoleto perchè ora salviamo id dettagli in fattura)
    public function updatedContract()
    {
        return $this->contract
            ? $this->contract->contractDetails()
                ->where('date', '<=', $this->invoice_date)
                ->orderByDesc('date')
                ->first()
            : null;
    }

    // Crea l'identificativo della fattura (Repertorio)
    public function getInvoiceNumber()
    {
        $number = "";
        for($i=strlen($this->number);$i<3;$i++)
        {
            $number.= "0";
        }
        $number = $number.$this->number;
        return $number."/0".$this->section."/".$this->year;
    }

    // Crea la stringa con l'dentificativo da aggiungere al nome del pdf della fattura
    public function printNumber()
    {
        $number = $this->getNewInvoiceNumber();
        return str_replace('/', '_', $number);
    }

    // Calcola il totale a doversi da mostrare in tabella
    public function getResidue()
    {
        $total = floatval($this->total ?? 0);
        $no_vat_total = floatval($this->no_vat_total ?? 0);
        $totalPayment = floatval($this->total_payment ?? 0);
        $totalNotes = floatval($this->total_notes ?? 0);
        if($this->client->type->value == 'public')
            return $no_vat_total - ($totalPayment + $totalNotes);
        else
            return $total - ($totalPayment + $totalNotes);
    }

    // Calcola l'importo IVA da mostrare in tabella
    public function getVat()
    {
        $total = $this->invoiceItems()->where('auto', false)->sum('total');
        $no_vat_total = $this->invoiceItems()->where('auto', false)->sum('amount');
        return $total - $no_vat_total;
    }

    // Crea l'identificativo della fattura (Fatturazione attiva)
    public function getNewInvoiceNumber()
    {

        if($this->art_73) {
            $number = "";
            $date = $this->invoice_date->format('Y-m-d');
            for($i=strlen($this->number);$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$this->number;
            return $number."/".$date;
        }
        else{
            $number = "";
            $sectional = Sectional::find($this->sectional_id)->description;
            for($i=strlen($this->number);$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$this->number;
            return $number."/".$sectional."/".$this->year;
        }

    }

    // Query per fatture in 'Repertorio'
    public function scopeOldInvoices($query)
    {
        $tenant = Filament::getTenant();
        return $query->whereNull('flow')
                     ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id));
    }

    // Query per fatture in 'Fatturazione attiva'
    public function scopeNewInvoices($query)
    {
        $tenant = Filament::getTenant();
        return $query->where('flow', 'out')
                     ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id))
                     ->orderByRaw("FIELD(sdi_status, 'rifiutata', 'scartata') DESC")
                     ->orderBy('invoice_date', 'desc')
                     ->orderBy('year', 'desc')
                     ->orderBy('sectional_id', 'asc')
                     ->orderBy('number', 'desc');
    }

    // Aggiorna i totali (con e senza IVA della fattura) ad ogni inserimento di una voce
    public function updateTotal(): void
    {
        $total = $this->invoiceItems()->sum('total');
        // $total = $this->invoiceItems()->where('auto', false)->sum('total');
        $no_vat_total = $this->invoiceItems()->where('auto', false)->sum('amount');
        $this->vat = $total - $no_vat_total;
        $this->total = $total;
        $this->no_vat_total = $no_vat_total;
        $this->save();
    }

    // Aggiorna il totale della note di credito riferite alla fattura
    public function updateTotalNotes(): void
    {
        $total = $this->creditNotes()->sum('total');
        $this->total_notes = $total;
        $this->save();
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            $invoice->contract_detail_id = $invoice->updatedContract()->id; // Cristallizza nella fattura lo stato dei dettagli del contratto
            $invoice->flow = 'out';                                         // Indica che la fattura è in uscita (attiva)
        });

        static::created(function ($invoice) {
            if ($invoice->invoice) {
                $invoice->invoice->updateTotalNotes();                      // Tiene aggiornato il totale delle note di credito della fattura parent (nel caso di nota di credito) se creata una nuova
            }
        });

        static::updating(function ($invoice) {
            //
        });

        static::updated(function ($invoice) {
            if ($invoice->invoice) {
                $invoice->invoice->updateTotalNotes();                      // Tiene aggiornato il totale delle note di credito della fattura parent (nel caso di nota di credito) se modificata una esistente
            }
        });

        static::deleted(function ($invoice) {
            if ($invoice->invoice) {
                $invoice->invoice->updateTotalNotes();                      // Tiene aggiornato il totale delle note di credito della fattura parent (nel caso di nota di credito) se eliminata una esistente
            }
        });
    }

    // Crea l'array di dati per la sezione riepiloghi IVA
    public function vatResume(): array
    {
        $vats = [];
        $items = $this->invoiceItems instanceof \Illuminate\Support\Collection
            ? $this->invoiceItems->where('auto', false)
            : $this->invoiceItems()->where('auto', false)->get();
        foreach ($items as $item) {
            $rate = $item->vat_code_type->value;
            if (!isset($vats[$rate])) {
                $vats[$rate] = [
                    'norm' => $item->vat_code_type->getRate() == '0'
                                ? 'ART. 15 DPR 633/72'
                                : ($this->client?->type?->value == 'public'
                                    ? 'S (scissione dei pagamenti)'
                                    : (($this->company->fiscalProfile->tax_regime->value == 'rf16' || $this->company->fiscalProfile->tax_regime->value == 'rf17')
                                        ? 'D (esigibilità differita)'
                                        : 'I (esigibilità immediata')),
                    // 'norm' => $item->vat_code_type->getRate() == '0'
                    //             ? 'ART. 15 DPR 633/72'
                    //             :  $this->vat_enforce_type?->getCode()."(".$this->vat_enforce_type?->getLabel().")",
                    '%' => $item->vat_code_type->getRate() == '0' ? $item->vat_code_type->getCode() : $item->vat_code_type->getRate(),
                    'taxable' => 0,
                    'vat' => 0,
                    'total' => 0,
                    'free' => $item->vat_code_type->getRate() == '0'
                ];
            }
            $vats[$rate]['taxable'] += $item->amount;
            $vats[$rate]['vat'] += $item->total - $item->amount;
            $vats[$rate]['total'] += $item->total;
        }
        return $vats;
    }

    // Calcola imponibile per casse previdenziali e ritenute
    public function getTaxable()
    {
        $taxRegime = $this->company->fiscalProfile->tax_regime->value;
        $base = 0;
        $items = $this->invoiceItems instanceof \Illuminate\Support\Collection
            ? $this->invoiceItems->where('auto', false)
            : $this->invoiceItems()->where('auto', false)->get();
        foreach ($items as $item) {
            $vatCodeType = $item->vat_code_type;
            $vatRate = floatval($item->vat_code_type->getRate());

            // Regime ordinario/semplificato: considera solo righe con IVA > 0
            if ($taxRegime !== 'rf19' && $vatRate > 0) {
                $base += floatval($item->amount);
            }
            // Regime forfettario: considera tutte le righe tranne N1 (VC06, VC06A)
            elseif ($taxRegime === 'rf19' &&
                    !in_array($vatCodeType, [VatCodeType::VC06, VatCodeType::VC06A])) {
                $base += floatval($item->amount);
            }
        }
        return $base;
    }

    // Crea l'array di dati per la sezione delle casse previdenziali
    public function getFundBreakdown(): array
    {
        $results = [];

        // Recupera il regime fiscale dell'azienda
        $taxRegime = $this->company->fiscalProfile->tax_regime->value;

        $selectedIds = is_array($this->social_contributions) ? $this->social_contributions : [];

        // $contributions = $this->company->socialContributions;dd($contributions);
        // Filtra le socialContributions
        $contributions = $this->company->socialContributions->filter(function ($item) use ($selectedIds) {
            return in_array($item->id, $selectedIds);
        });
        // dd($contributions);

        // Itera sulle casse previdenziali selezionate tra quelle associate all'azienda
        foreach ($contributions as $contribution) {
            $taxablePerc = floatval($contribution->taxable_perc); // Percentuale imponibile (es. 100)
            $rate = floatval($contribution->rate); // Aliquota cassa (es. 4% per INPS)
            $fundCode = $contribution->fund->getCode(); // Codice SdI (es. TC22 per INPS)
            $fundDescription = $contribution->fund->getDescription(); // Descrizione (es. INPS)
            $vatCode = $contribution->vat_code; // Codice IVA per il contributo (es. VC01 per 22%)

            // Calcola l'imponibile per la cassa
            // $base = 0;
            // foreach ($this->invoiceItems as $item) {
            //     $vatCodeType = $item->vat_code_type;
            //     $vatRate = floatval($item->vat_code_type->getRate());

            //     // Regime ordinario/semplificato: considera solo righe con IVA > 0
            //     if ($taxRegime !== 'rf19' && $vatRate > 0) {
            //         $base += floatval($item->amount);
            //     }
            //     // Regime forfettario: considera tutte le righe tranne N1 (VC06, VC06A)
            //     elseif ($taxRegime === 'rf19' &&
            //             !in_array($vatCodeType, [VatCodeType::VC06, VatCodeType::VC06A])) {
            //         $base += floatval($item->amount);
            //     }
            // }
            $base = $this->getTaxable();

            // Calcola l'imponibile effettivo e il contributo
            $taxableBase = round($base * ($taxablePerc / 100), 2);                                  // Imponibile effettivo in base a % imponibile indicata
            $amount = round($taxableBase * ($rate / 100), 2);                                       // Importo contributo

            // Calcola l'IVA sul contributo, se applicabile
            $vatRate = $vatCode ? floatval($vatCode->getRate()) : 0;                                // Aliquota IVA cassa previdenziale
            $vatAmount = ($taxRegime !== 'rf19' && $vatRate > 0)                                    // Importo IVA cassa previdenziale
                ? round($amount * ($vatRate / 100), 2)
                : 0;
            $vatCodeValue = $vatCode->getRate() == 0 ? $vatCode->getCode() : $vatCode->getRate();   // %IVA

            // Prepara i dati per la stampa e l'XML SdI
            $results[] = [
                'fund_code' => $fundCode,                                                           // es. TC22
                'fund' => "{$contribution->fund->forPrint()}",                                      // es. TC22 (INPS)
                'vat_code' => $vatCode?->value,                                                     // es. vc01
                '%' => $vatCodeValue,                                                               // Aliquota IVA o codice esenzione
                'rate' => $rate,                                                                    // % contributo
                'taxable_base' => $taxableBase,                                                     // Imponibile cassa
                'amount' => $amount,                                                                // Contributo previdenziale
                'vat' => $vatAmount,                                                                // Importo IVA sul contributo
            ];
        }

        return $results;
    }

    // Aggiorna l'array di dati per la sezione riepiloghi IVA con i dati delle casse previdenziali
    public function updateResume($vats, $funds): array
    {
        foreach ($vats as &$vat) {
            foreach ($funds as $fund) {
                if ($fund['%'] == $vat['%']) {
                    $vat['taxable'] += $fund['amount'];
                }
            }
            if (!$vat['free']) {
                $vat['vat'] = $vat['taxable'] * ((int) $vat['%'] / 100);
            }
            $vat['total'] = $vat['taxable'] + $vat['vat'];
        }
        return $vats;
    }

    // Verifica se ci sono le condizioni per inserire l'imposta di bollo (gli importi esenti IVA sono uguali o superiori al valore indicato) e nel caso lo fa
    public function checkStampDuty()
    {
        $stampDuty = $this->company->stampDuty;
        if($stampDuty->active){
            $vats = $this->vatResume();
            $free = 0;
            $insert = true;
            foreach($vats as $key => $vat){
                if($key == 'vc06a') $insert = false;
                if($vat['free']) $free += $vat['taxable'];
            }
            if($insert && $free >= $stampDuty->value){
                $a = [
                    'invoice_id' => $this->id,
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
                InvoiceItem::where('invoice_id', $this->id)
                    ->where('vat_code_type', 'vc06a')
                    ->delete();
            }
        }
    }
}
