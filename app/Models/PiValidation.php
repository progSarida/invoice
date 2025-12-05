<?php

namespace App\Models;

use App\Enums\PiValidationStatus;
use Illuminate\Database\Eloquent\Model;

class PiValidation extends Model
{
    protected $fillable = [
        'name',
        'description',
        'order',
        'pi_validation_status'
    ];

    protected $casts = [
        'pi_validation_status' => PiValidationStatus::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
