<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    //
    public function provinces(){
        $this->hasMany(Province::class);
    }
}
