<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentType extends Model
{
    protected $fillable = [
        'order',
        'name',
        'description',
    ];
    
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
