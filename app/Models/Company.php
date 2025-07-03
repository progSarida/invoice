<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    //
    protected $fillable = [
        'name',
        'vat_number',
        'address',
        'city_code',
        'is_active',
        'email',
        'pec',
        'phone',
        'fax',
        'tax_number',
        'register',
        'register_province_id',
        'register_number',
        'register_date',
        'rea_province_id',
        'rea_number',
        'nominal_capital',
        'shareholders',
        'liquidation'
    ];

    public function users(){
        return $this->belongsToMany(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_code', 'code');
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function curator()
    {
        return $this->hasOne(Curator::class);
    }

    public function productor()
    {
        return $this->hasOne(Productor::class);
    }

    public function fiscalProfile()
    {
        return $this->hasOne(FiscalProfile::class);
    }

    public function socialContributions()
    {
        return $this->hasMany(SocialContribution::class);
    }

    public function withholdings()
    {
        return $this->hasMany(Withholding::class);
    }

    public function stampDuty()
    {
        return $this->hasOne(StampDuty::class);
    }

    public function sectionals()
    {
        return $this->hasMany(Sectional::class);
    }

    public function bankAccounts(){
        return $this->hasMany(BankAccount::class);
    }

    public function invoices(){
        return $this->hasMany(Invoice::class);
    }

    public function activePayments(){
        return $this->hasMany(ActivePayments::class);
    }

    public function registerProvince(){
        return $this->belongsTo(Province::class, 'register_province_id');
    }

    public function reaProvince(){
        return $this->belongsTo(Province::class, 'rea_province_id');
    }

    public function docTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocType::class, 'company_docs', 'company_id', 'doc_type_id')
                    ->withTimestamps();
    }

    public function newContracts()
    {
        return $this->hasMany(NewContract::class);
    }

    public function invoicesElements()
    {
        return $this->hasMany(InvoiceElement::class);
    }
}
