<?php
namespace App\Models;

use App\Enums\InvoicingCicle;
use App\Enums\TaxType;
use App\Enums\TenderPaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewContract extends Model
{
    protected $fillable = [
        'company_id',
        'client_id',
        'tax_types',
        'start_validity_date',
        'end_validity_date',
        'accrual_types',
        'payment_type',
        'cig_code',
        'cup_code',
        'office_code',
        'office_name',
        'amount',
        'invoicing_cycle',
        'new_contract_copy_path',
        'new_contract_copy_date',
        'reinvoice'
    ];

    protected $casts = [
        'tax_types' => 'array',
        'payment_type' => TenderPaymentType::class,
        'start_validity_date' => 'date',
        'end_validity_date' => 'date',
        'amount' => 'decimal:2',
        'invoicing_cycle' => InvoicingCicle::class,
        'new_contract_copy_date' => 'date',
        'reinvoice' => 'boolean',
        'accrual_types' => 'json',
    ];

    public function getTaxTypesAttribute($value)
    {
        $values = is_string($value) ? json_decode($value, true) : $value;
        return array_map(function ($val) {
            return TaxType::from(trim(strtolower($val)))->getLabel();
        }, $values ?? []);
    }

    public function setTaxTypesAttribute($values)
    {
        $this->attributes['tax_types'] = json_encode(array_map(function($value) {
            return strtolower(trim($value));
        }, $values));
    }

    public function getAccrualTypesAttribute($value)
    {
        $values = is_string($value) ? json_decode($value, true) : $value;
        if (!$values) return [];
        $accrualTypes = AccrualType::whereIn('id', $values)->pluck('name', 'id')->toArray();
        return array_map(function ($id) use ($accrualTypes) {
            return $accrualTypes[$id] ?? 'Sconosciuto';
        }, $values);
    }

    public function setAccrualTypesAttribute($values)
    {
        $this->attributes['accrual_types'] = json_encode($values);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function contractDetails(): HasMany
    {
        return $this->hasMany(ContractDetail::class, 'contract_id');
    }

    public function lastDetail()
    {
        return $this->hasOne(ContractDetail::class, 'contract_id')->latestOfMany('date');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'contract_id');
    }

    protected static function booted()
    {
        static::creating(function ($contract) {
            //
        });

        static::created(function ($contract) {
            //
        });

        static::updating(function ($contract) {
            //
        });

        static::saved(function ($contract) {
            //
        });

        static::deleting(function ($contract) {
            //
        });

    }
}

