<?php

namespace App\Models;

use App\Enums\BailStatus;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;

class Bail extends Model
{
    protected $fillable = [
        'client_id',                                            // id cliente
        'contract_id',                                          // id contratto
        'cig_code',                                             // codice identificativo gara
        'tax_type',                                             // tipo entrata (Enum)
        'insurance_id',                                            // assicurazione
        'agency_id',                                               // agenzia
        'bill_number',                                          // numero polizza
        'bill_date',                                            // data polizza
        'bill_attachment_path',                                 // percorso file polizza
        'bill_start',                                           // inizio polizza
        'bill_deadline',                                        // scadenza polizza
        'year_duration',                                        // durata polizza
        'month_duration',                                       // durata polizza
        'day_duration',                                         // durata polizza
        'original_premium',                                     // importo
        'original_pay_date',                                    // data pagamento premio originario
        'bail_status',                                          // stato cauzione (Enum)
        'release_date',                                         // scadenza polizza
        'renew_premium',                                        // importo
        'renew_date',                                           // scadenza polizza
        'receipt_attachment_path',                              // percorso file ricevuta di quietanza
        'note',                                                 // note
    ];

    protected $casts = [
        'tax_type' => TaxType::class,
        'bail_status' => BailStatus::class
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }

    public function contract(){
        return $this->belongsTo(NewContract::class,'contract_id');
    }

    public function insurance(){
        return $this->belongsTo(Insurance::class);
    }

    public function agency(){
        return $this->belongsTo(Agency::class);
    }
}
