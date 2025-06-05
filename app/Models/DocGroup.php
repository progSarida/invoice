<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocGroup extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];
}
