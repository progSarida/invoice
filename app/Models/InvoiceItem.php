<?php

namespace App\Models;

use App\Enums\SdiStatus;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    //

    protected $fillable = [
        'invoice_id',

    ];
    


    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }
}
