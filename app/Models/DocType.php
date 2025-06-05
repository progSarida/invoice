<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocType extends Model
{
    protected $fillable = [
        'doc_group_id',
        'name',
        'description'
    ];

    public function docGroup()
    {
        return $this->belongsTo(DocGroup::class);
    }

    // public static function optionsWithDescription(): array
    // {
    //     return self::all()
    //         ->keyBy('id')
    //         ->map(fn ($docType) => "{$docType->name} - {$docType->description}")
    //         ->toArray();
    // }

    public static function groupedOptions(): array
    {
        return self::with('docGroup')->get()
            ->groupBy(fn($docType) => $docType->docGroup ? $docType->docGroup->name : 'Senza gruppo')
            ->mapWithKeys(function ($grouped, $groupName) {
                return [
                    $groupName => $grouped->mapWithKeys(fn($docType) => [
                        $docType->id => "{$docType->name} - {$docType->description}"
                    ])->toArray()
                ];
            })
            ->toArray();
    }
}
