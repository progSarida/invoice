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
        'client_id',
        'tax_type',
        'start_validity_date',
        'end_validity_date',
        'accrual_type_id',
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
        'tax_type' => TaxType::class,
        'payment_type' => TenderPaymentType::class,
        'start_validity_date' => 'date',
        'end_validity_date' => 'date',
        'amount' => 'decimal:2',
        'invoicing_cycle' => InvoicingCicle::class,
        'new_contract_copy_date' => 'date',
        'reinvoice' => 'boolean',
        // 'accrual_types' => 'array'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function accrualType(): BelongsTo
    {
        return $this->belongsTo(AccrualType::class);
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
}
