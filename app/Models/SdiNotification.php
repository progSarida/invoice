<?php

namespace App\Models;

use App\Enums\SdiStatus;
use Illuminate\Database\Eloquent\Model;

class SdiNotification extends Model
{
    //

    protected $fillable = [
        'invoice_id',
        'code',
        'status',
        'date',
        'description',
    ];
    
    protected $casts = [
         'status' => SdiStatus::class
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }
}
