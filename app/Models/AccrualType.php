<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccrualType extends Model
{
    protected $fillable = [
        'order',
        'name',
        'description',
    ];
}
