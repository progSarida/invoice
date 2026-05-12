<?php

namespace App\Models;

use App\Enums\NotifyType;
use Illuminate\Database\Eloquent\Model;

class ShipmentType extends Model
{
    protected $fillable = [
        'order',
        'name',
        'description',
        'notify_type',
    ];

    protected $casts = [
        'notify_type' => NotifyType::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
