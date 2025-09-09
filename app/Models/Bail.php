<?php
namespace App\Models;

use App\Enums\BailStatus;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;

class Bail extends Model
{
    protected $fillable = [
        'client_id',                                                // id cliente
        'contract_id',                                              // id contratto
        'cig_code',                                                 // codice identificativo gara
        'tax_types',                                                // MODIFICA: Rinominato da 'tax_type' a 'tax_types' per supporto multiplo
        'insurance_id',                                             // assicurazione
        'agency_id',                                                // agenzia
        'bill_number',                                              // numero polizza
        'bill_date',                                                // data polizza
        'bill_attachment_path',                                     // percorso file polizza
        'bill_start',                                               // inizio polizza
        'bill_deadline',                                            // scadenza polizza
        'year_duration',                                            // durata polizza
        'month_duration',                                           // durata polizza
        'day_duration',                                             // durata polizza
        'original_premium',                                         // importo
        'original_pay_date',                                        // data pagamento premio originario
        'bail_status',                                              // stato cauzione (Enum)
        'release_date',                                             // scadenza polizza
        'renew_premium',                                            // importo
        'renew_date',                                               // scadenza polizza
        'receipt_attachment_path',                                  // percorso file ricevuta di quietanza
        'note',                                                     // note
    ];

    protected $casts = [
        'tax_types' => 'array',                                     // MODIFICA: Cambiato da 'TaxType::class' a 'array' per supporto multiplo
        'bail_status' => BailStatus::class
    ];

    // MODIFICA: Aggiunto getter per tax_types
    public function getTaxTypesAttribute($value)
    {
        $values = is_string($value) ? json_decode($value, true) : $value;
        return array_map(function ($val) {
            return TaxType::from($val)->getLabel(); // Converte il valore enum in etichetta
        }, $values ?? []);
    }

    // MODIFICA: Aggiunto setter per tax_types
    public function setTaxTypesAttribute($values)
    {
        $this->attributes['tax_types'] = json_encode(array_map('strtolower', $values));
    }

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

    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}