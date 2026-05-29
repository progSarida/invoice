<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelType extends Model
{
    protected $fillable = [
        'order',
        'name',
        'description',
    ];

    protected $casts = [
        //
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function modelSubtypes()
    {
        return $this->hasMany(Template::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class);
    }
}
