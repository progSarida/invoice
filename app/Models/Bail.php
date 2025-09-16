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
        'receipt_date',                                             // data ricevuta di quietanza
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

    protected static function booted()
    {
        static::creating(function ($bail) {
            //
        });

        static::created(function ($bail) {
            //
        });

        static::updating(function ($bail) {
            //
        });

        static::saved(function ($bail) {

            $existB = Attachment::where('attachment_type', 'bail_bill')->where('element_id', $bail->id)->first();       // controllo se esiste l'allegato della polizza
            $existR = Attachment::where('attachment_type', 'bail_receipt')->where('element_id', $bail->id)->first();    // controllo se esiste l'allegato della ricevuta

            if($bail->bill_attachment_path){
                $filenameB = basename($bail->bill_attachment_path) ?: 'unknown';

                $dataB = [
                    'company_id' => \Filament\Facades\Filament::getTenant()->id,
                    'client_id' => $bail->contract->client_id,
                    'contract_id' => $bail->contract->id,
                    // 'element_table' => 'bails',
                    'element_id' => $bail->id,
                    'attachment_type' => 'bail_bill',
                    'attachment_filename' => $filenameB,
                    'attachment_date' => $bail->bill_date,
                    'attachment_upload_date' => today()->toDateString(),
                    'attachment_path' => $bail->bill_attachment_path,
                ];

                if (!$existB) { $billAttachment = Attachment::create($dataB); }
                else { $existB->update($dataB); }
            }
            else { $existB->delete(); }

            if($bail->receipt_attachment_path){
                $filenameR = basename($bail->receipt_attachment_path) ?: 'unknown';

                $dataR = [
                    'company_id' => \Filament\Facades\Filament::getTenant()->id,
                    'client_id' => $bail->contract->client_id,
                    'contract_id' => $bail->contract->id,
                    // 'element_table' => 'bails',
                    'element_id' => $bail->id,
                    'attachment_type' => 'bail_receipt',
                    'attachment_filename' => $filenameR,
                    'attachment_date' => $bail->receipt_date,
                    'attachment_upload_date' => today()->toDateString(),
                    'attachment_path' => $bail->receipt_attachment_path,
                ];

                if (!$existR) { $billAttachment = Attachment::create($dataR); }
                else { $existR->update($dataR); }
            }
            else { $existR->delete(); }

        });

        static::deleting(function ($bail) {
            //
        });

        static::deleted(function ($bail) {
            $existB = Attachment::where('attachment_type', 'bail_bill')->where('element_id', $bail->id)->first();       // controllo se esiste l'allegato della polizza
            $existR = Attachment::where('attachment_type', 'bail_receipt')->where('element_id', $bail->id)->first();    // controllo se esiste l'allegato della ricevuta

            if($existB) { $existB->delete(); }
            if($existR) { $existR->delete(); }
        });

    }
}
