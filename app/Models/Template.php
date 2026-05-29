<?php

namespace App\Models;

use App\Enums\ModelGroup;
use App\Models\ModelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Template extends Model
{
    protected $fillable = [
        'company_id',                                                   // id tenant
        'model_type_id',                                                // tipo di modello
        'model_subtype_id',                                             // sottotipo di modello
        'filename',                                                     // nome file modello
        'description',                                                     // descrizione modello
        'upload_date',                                                  // data caricamento modello
        'path',                                                         // percorso file modello
        'current',                                                      // flag modello in vigore
    ];

    protected $casts = [
        'upload_date' => 'date',
        'current' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function modelType()
    {
        return $this->belongsTo(ModelType::class, 'model_type_id');
    }

    public function modelSubType()
    {
        return $this->belongsTo(ModelSubType::class, 'model_subtype_id');
    }

    protected static function booted()
    {
        static::creating(function ($template) {
            $template->upload_date = today();
        });

        static::created(function ($template) {
            //
        });

        static::updating(function ($template) {
            //
        });

        static::saved(function ($template) {
            //
        });

        static::deleting(function ($template) {
            //
        });

        static::deleted(function ($template) {
            if ($template->path) {
                $disk = Storage::disk(config('filesystems.default'));
                $folder = 'templates/' . $template->id;
                $disk->deleteDirectory($folder);
            }
        });

    }
}
