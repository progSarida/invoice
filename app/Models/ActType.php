<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActType extends Model
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
