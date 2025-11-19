<?php

namespace App\Models;

use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;

class Container extends Model
{

    protected $casts = [
        'tax_types' => 'array',
        'accrual_types' => 'array'
    ];
    
    protected $fillable = [
        'client_id',
        'name',
        'tax_types',
        'accrual_types',
        'tax_types_json'
    ];
    //
    public function getTaxTypesAttribute($value)
    {
        $values = explode(', ', $value);
        foreach($values as $key => $value){
            $values[$key] = strtoupper($value);
        }
        return $values;
    }

    public function setTaxTypesAttribute($values)
    {
        foreach($values as $key => $value){
            $values[$key] = strtolower($value);
        }
        $this->attributes['tax_types'] = implode(', ', $values);
    }

     public function getAccrualTypesAttribute($value)
    {
        $values = explode(', ', $value);
        foreach($values as $key => $value){
            switch($value){
                case "ordinary": $values[$key] = "Competenza ordinaria"; break;
                case "coercive": $values[$key] = "Competenza coattiva"; break;
            }
        }
        return $values;
    }

    public function setAccrualTypesAttribute($values)
    {
        foreach($values as $key => $value){
            switch($value){
                case "Competenza ordinaria": $values[$key] = "ordinary"; break;
                case "Competenza coattiva": $values[$key] = "coercive"; break;
            }
        }
        $this->attributes['accrual_types'] = implode(', ', $values);
    }

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }

    public function contracts(){
        return $this->hasMany(Contract::class);
    }

    public function tender(){
        return $this->hasOne(Tender::class);
    }
    
}
