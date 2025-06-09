<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocType extends Model
{
    protected $fillable = [
        'doc_group_id',
        'name',
        'description'
    ];

    public function docGroup(): BelongsTo
    {
        return $this->belongsTo(DocGroup::class, 'doc_group_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_docs', 'doc_type_id', 'company_id')
                    ->withTimestamps();
    }

    public static function groupedOptions($companyId = null): array
    {
        $query = self::query();
        if ($companyId) {
            $query->whereHas('companies', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query->with('docGroup')
            ->get()
            ->groupBy(fn($docType) => $docType->docGroup ? $docType->docGroup->name : 'Senza gruppo')
            ->mapWithKeys(function ($grouped, $groupName) {
                return [
                    $groupName => $grouped->mapWithKeys(fn($docType) => [
                        $docType->id => $docType->name . ($docType->description ? " - {$docType->description}" : '')
                    ])->toArray()
                ];
            })
            ->toArray();
    }

    public static function flatOptions($companyId = null): array
    {
        $query = self::query();
        if ($companyId) {
            $query->whereHas('companies', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query->with('docGroup')
            ->get()
            ->mapWithKeys(function ($docType) {
                $groupName = $docType->docGroup ? $docType->docGroup->name : 'Senza gruppo';
                $label = $docType->name . ($docType->description ? " - {$docType->description}" : '') . " ($groupName)";
                return [$docType->id => $label];
            })
            ->toArray();
    }

    public function sectionals(): BelongsToMany
    {
        return $this->belongsToMany(Sectional::class, 'doc_type_sectional', 'doc_type_id', 'sectional_id');
    }
}
