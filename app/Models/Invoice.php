<?php

namespace App\Models;

use App\Enums\TaxType;
use App\Enums\SdiStatus;
// use App\Enums\AccrualType;
use App\Enums\TimingType;
use App\Enums\InvoiceType;
use App\Enums\PaymentType;
use App\Models\AccrualType;
use App\Enums\PaymentStatus;
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
        'bank_account_id',
        'payment_type',
        'payment_days',
    ];

    protected $casts = [
        'tax_type' =>  TaxType::class,
        'invoice_type' => InvoiceType::class,
        // 'accrual_type' => AccrualType::class,
        'payment_status' => PaymentStatus::class,
        'payment_type' => PaymentType::class,
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

    public function sectional(){
        return $this->belongsTo(Sectional::class,'sectional_id');
    }

    public function contract(){
        return $this->belongsTo(NewContract::class,'contract_id');
    }

    public function creditNotes(){
        return $this->hasMany(Invoice::class, 'parent_id', 'id');
    }

    public function getInvoiceNumber(){
        $number = "";
        for($i=strlen($this->number);$i<3;$i++)
        {
            $number.= "0";
        }
        $number = $number.$this->number;
        return $number."/0".$this->section."/".$this->year;
    }

    public function printNumber(){
        $number = $this->getNewInvoiceNumber();
        return str_replace('/', '_', $number);
    }

    public function getResidue()
    {
        $total = floatval($this->total ?? 0);
        $totalPayment = floatval($this->total_payment ?? 0);
        $totalNotes = floatval($this->total_notes ?? 0);
        return $total - ($totalPayment + $totalNotes);
    }

    public function getNewInvoiceNumber(){

        $number = "";
        $sectional = Sectional::find($this->sectional_id)->description;
        for($i=strlen($this->number);$i<3;$i++)
        {
            $number.= "0";
        }
        $number = $number.$this->number;
        return $number."/".$sectional."/".$this->year;

    }

    public function accrualType(){
        return $this->belongsTo(AccrualType::class,'accrual_type_id');
    }

    public function manageType(){
        return $this->belongsTo(ManageType::class,'manage_type_id');
    }

    public function scopeOldInvoices($query)
    {
        $tenant = Filament::getTenant();
        return $query->whereNull('flow')
                     ->when($tenant, fn ($query) => $query->where('company_id', $tenant->id));
    }

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

    public function updateTotal(): void
    {
        $total = $this->invoiceItems()->sum('total');
        $this->total = $total;
        $this->save();
    }

    public function updateTotalNotes(): void
    {
        $total = $this->creditNotes()->sum('total');
        $this->total_notes = $total;
        $this->save();
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            $invoice->flow = 'out';
        });

        static::created(function ($invoice) {
            if ($invoice->invoice) {
                $invoice->invoice->updateTotalNotes();
            }
        });

        static::updating(function ($invoice) {
            //
        });

        static::updated(function ($invoice) {
            if ($invoice->invoice) {
                $invoice->invoice->updateTotalNotes();
            }
        });

        static::deleted(function ($invoice) {
            if ($invoice->invoice) {
                $invoice->invoice->updateTotalNotes();
            }
        });
    }
}
