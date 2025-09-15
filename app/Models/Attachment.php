<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'company_id',                                               // id tenant
        'client_id',                                                // id cliente
        'contract_id',                                              // id contratto
        'element_id',                                               // id elemento che ha caricato il file
        'attachment_type',                                          // tipo di allegato
        'attachment_filename',                                      // nome file allegato
        'attachment_date',                                          // dati caricamento allegato
        'attachment_path',                                          // percorso file allegato
    ];

    protected $casts = [
        'attachment_type' => AttachmentType::class,
        'attachemnt_date' => 'date'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function contract()
    {
        return $this->belongsTo(NewContract::class, 'contract_id');
    }

    // public function element()
    // {
    //     return $this->morphTo('element', 'element_table', 'element_id');
    // }
}
