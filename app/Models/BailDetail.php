<?php

namespace App\Models;

use App\Enums\BailStatus;
use Illuminate\Database\Eloquent\Model;

class BailDetail extends Model
{
    protected $fillable = [
        'bail_id',                                                  // id polizza
        'bill_start',                                               // data inizio polizza
        'bill_deadline',                                            // data scadenza polizza
        'premium',                                                  // premio
        'bail_status',                                              // stato polizza
        'pay_date',                                                 // data pagamento
        'receipt_date',                                             // data quietanza
        'attachment_path',                                          // percorso allegato
        'release_date',                                             // data svincolo
        'release_path',                                             // percorso svincolo
    ];

    protected $casts = [
        'bail_status' => BailStatus::class,
        'bill_start' => 'date',
        'bill_deadline' => 'date',
        'pay_date' => 'date',
        'receipt_date' => 'date',
        'release_date' => 'date',
    ];

    public function bail()
    {
        return $this->belongsTo(Bail::class);
    }

    protected static function booted()
    {
        static::creating(function ($detail) {
            //
        });

        static::created(function ($detail) {
            //
        });

        static::updating(function ($detail) {
            //
        });

        static::saved(function ($detail) {
            $mostRecentDetail = $detail->bail->bailDetails()                    // recupero il dettaglio di contratto più recente
                ->orderBy('receipt_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

                if ($mostRecentDetail && $mostRecentDetail->id === $detail->id) {           // controllo che sia il dettaglio di contratto più recente

                    $filenameA = basename($detail->attachment_path) ?: 'unknown';
                    if($detail->attachment_path){
                        $dataA = [
                            'company_id' => \Filament\Facades\Filament::getTenant()->id,
                            'client_id' => $detail->bail->client_id,
                            'contract_id' => $detail->bail->contract?->id,
                            // 'element_table' => 'new_contracts',
                            'element_id' => $detail->id,
                            'attachment_type' => 'bail_detail',
                            'attachment_filename' => $filenameA,
                            'attachment_date' => $detail->receipt_date,
                            'attachment_upload_date' => now()->toDateString(),
                            'attachment_path' => $detail->attachment_path,
                        ];
                        $exist = Attachment::where('attachment_type', 'bail_detail')->where('element_id', $detail->id)->first();
                        if (!$exist) { $bailAttachment = Attachment::create($dataA); }
                        else { $exist->update($dataA); }
                    }

                    if($detail->release_path){
                        $filenameR = basename($detail->release_path) ?: 'unknown';
                        $dataR = [
                            'company_id' => \Filament\Facades\Filament::getTenant()->id,
                            'client_id' => $detail->bail->client_id,
                            'contract_id' => $detail->bail->contract?->id,
                            // 'element_table' => 'new_contracts',
                            'element_id' => $detail->id,
                            'attachment_type' => 'bail_release',
                            'attachment_filename' => $filenameR,
                            'attachment_date' => $detail->receipt_date,
                            'attachment_upload_date' => now()->toDateString(),
                            'attachment_path' => $detail->release_path,
                        ];
                        $exist = Attachment::where('attachment_type', 'bail_release')->where('element_id', $detail->id)->first();
                        if (!$exist) { $bailAttachment = Attachment::create($dataR); }
                        else { $exist->update($dataR); }
                    }
                }
        });

        static::deleting(function ($detail) {
            //
        });

        static::deleted(function ($detail) {
            $existD = Attachment::where('attachment_type', 'bail_detail')->where('element_id', $detail->id)->first();       // controllo se esiste l'allegato della quietanza

            if($existD) { $existD->delete(); }
        });

    }
}
