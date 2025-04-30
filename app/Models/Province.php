<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    //

    public function region(){
        $this->belongsTo(Region::class);
    }

    public function cities(){
        $this->hasMany(City::class);
    }
}
