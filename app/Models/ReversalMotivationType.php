<?php

namespace App\Models;

use App\Enums\ReversalGroupType;
use Illuminate\Database\Eloquent\Model;

class ReversalMotivationType extends Model
{
    protected $fillable = [
        'reversal_group_type',
        'name',
        'order',
        'description',
    ];

    protected $casts = [
         'reversal_group_type' => ReversalGroupType::class
    ];
}
