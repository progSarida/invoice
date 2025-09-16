<?php

namespace App\Models;

use App\Enums\ContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDetail extends Model
{
    protected $fillable = [
        'contract_id',
        'number',
        'contract_type',
        'date',
        'description',
        'contract_attachment_path',
        'contract_attachment_date',
    ];

    protected $casts = [
        'date' => 'date',
        'contract_type' => ContractType::class
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(NewContract::class, 'contract_id');
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
            $mostRecentDetail = $detail->contract->contractDetails()                    // recupero il dettaglio di contratto più recente
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($mostRecentDetail && $mostRecentDetail->id === $detail->id) {           // controllo che sia il dettaglio di contratto più recente
                $updateData = [];
                if (!is_null($detail->contract_attachment_path)) {
                    $updateData['new_contract_copy_path'] = $detail->contract_attachment_path;
                }
                if (!is_null($detail->contract_attachment_date)) {
                    $updateData['new_contract_copy_date'] = $detail->contract_attachment_date;
                }
                if (!empty($updateData)) {
                    $detail->contract->update($updateData);
                }

                $filename = basename($detail->contract_attachment_path) ?: 'unknown';

                $dataA = [
                    'company_id' => \Filament\Facades\Filament::getTenant()->id,
                    'client_id' => $detail->contract->client_id,
                    'contract_id' => $detail->contract->id,
                    // 'element_table' => 'new_contracts',
                    'element_id' => $detail->contract->id,
                    'attachment_type' => 'contract',
                    'attachment_filename' => $filename,
                    'attachment_date' => $detail->date,
                    'attachment_upload_date' => now()->toDateString(),
                    'attachment_path' => $detail->contract_attachment_path,
                ];

                $exist = Attachment::where('attachment_type', 'contract')->where('element_id', $detail->contract->id)->first();

                if (!$exist) { $contractAttachment = Attachment::create($dataA); }
                else { $exist->update($dataA); }
            }
        });

        static::deleting(function ($detail) {
            //
        });

        static::deleted(function ($detail) {

            if (!$detail->contract) { return; }                                                             // controllo che il contratto associato esista

            $mostRecentDetail = $detail->contract->contractDetails()                                        // trovo il ContractDetail più recente rimasto
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $updateData = [];
            if ($mostRecentDetail) {                                                                        // se esiste un ContractDetail più recente, uso i suoi valori
                if (!is_null($mostRecentDetail->contract_attachment_path)) {
                    $updateData['new_contract_copy_path'] = $mostRecentDetail->contract_attachment_path;
                }
                if (!is_null($mostRecentDetail->contract_attachment_date)) {
                    $updateData['new_contract_copy_date'] = $mostRecentDetail->contract_attachment_date;
                }
            } else {                                                                                        // non esiste un ContractDetail più recente

                $updateData['new_contract_copy_path'] = null;
                $updateData['new_contract_copy_date'] = null;
            }

            if (!empty($updateData)) { $detail->contract->update($updateData); }                            // aggiorno NewContract se ci sono dati da aggiornare

            $exist = Attachment::where('element_table', 'new_contracts')->where('element_id', $detail->contract->id)->first();  // controllo se l'Attachment esiste già

            if ($mostRecentDetail && $exist) {
                $filename = basename($mostRecentDetail->contract_attachment_path) ?: 'unknown';             // aggiorno l'Attachment con i dati del ContractDetail più recente
                $dataA = [
                    'company_id' => \Filament\Facades\Filament::getTenant()->id,
                    'client_id' => $detail->contract->client_id,
                    'contract_id' => $detail->contract->id,
                    'element_table' => 'new_contracts',
                    'element_id' => $detail->contract->id,
                    'attachment_type' => 'contract',
                    'attachment_filename' => $filename,
                    'attachment_date' => $mostRecentDetail->date,
                    'attachment_upload_date' => now()->toDateString(),
                    'attachment_path' => $mostRecentDetail->contract_attachment_path,
                ];
                $exist->update($dataA);
            } elseif (!$mostRecentDetail && $exist) {
                $exist->delete();                                                                           // nessun ContractDetail rimasto, rimuovo l'Attachment
            }
        });

    }
}
