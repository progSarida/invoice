<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Province extends Model
{
    //

    protected $fillable = [
        'name',
        'code',
        'region_id',
    ];

    public function region(){
        return $this->belongsTo(Region::class,'region_id');
    }

    public function cities(){
        return $this->hasMany(City::class);
    }
}
