<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelSubType extends Model
{
    protected $fillable = [
        'model_type_id',
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

    public function modelType()
    {
        return $this->belongsTo(ModelType::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'model_subtype_id');
    }
}
