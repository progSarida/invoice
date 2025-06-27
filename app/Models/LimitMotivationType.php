<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LimitMotivationType extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
    ];
}
