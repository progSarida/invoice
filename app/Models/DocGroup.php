<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocGroup extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function docTypes(): HasMany
    {
        return $this->hasMany(DocType::class, 'doc_group_id');
    }
}
