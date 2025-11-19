<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'date',
        'instrument_id',
        'description',
        'client_id',
        'supplier_id',
        'in_amount',
        'out_amount',
        'progressive_balance'
    ];

    protected $casts = [
        //
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function instrument()
    {
        return $this->belongsTo(Instrument::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
