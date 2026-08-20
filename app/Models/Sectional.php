<?php

namespace App\Models;

// use App\Enums\DocType;
use App\Enums\ClientType;
use App\Enums\NumerationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Sectional extends Model
{
    protected $fillable = [
        'company_id',
        'description',
        'client_type',
        // 'doc_type',
        'doc_type_id',
        'numeration_type',
        'progressive'
    ];

    protected $casts = [
        'client_type' => ClientType::class,
        // 'doc_type' => DocType::class,
        'numeration_type' => NumerationType::class
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function getNumber(){
        return $this->progressive." / 0".$this->description." / ".date('Y');
    }

    // public function docType(): BelongsTo
    // {
    //     return $this->belongsTo(DocType::class, 'doc_type_id');
    // }

    public function docTypes(): BelongsToMany
    {
        // L'azienda viene ricavata dalla pivot e non da $this->company_id, altrimenti in
        // eager loading (Sectional::with('docTypes')) l'ordinamento andrebbe perso.
        return $this->belongsToMany(DocType::class, 'doc_type_sectional', 'sectional_id', 'doc_type_id')
            ->orderBy(                                                              // ordinamento definito in Dati azienda > Documenti
                DB::table('company_docs')
                    ->join('sectionals', 'sectionals.company_id', '=', 'company_docs.company_id')
                    ->select('company_docs.order')
                    ->whereColumn('company_docs.doc_type_id', 'doc_types.id')
                    ->whereColumn('sectionals.id', 'doc_type_sectional.sectional_id')
                    ->limit(1)
            )
            ->orderBy('doc_types.name');
    }
}
