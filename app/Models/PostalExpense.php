<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\ExpenseType;
use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\ReinvoiceType;
use App\Enums\ShipmentDocType;
use App\Enums\TaxType;
use App\Enums\VatCodeType;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;

class PostalExpense extends Model
{
    protected $fillable = [
        // informazioni base
        // 'company_id',
        'notify_type',
        'new_contract_id',
        'client_id',
        'tax_type',

        // protocollo e invio
        'send_protocol_number',
        'send_protocol_date',
        'shipment_type_id',
        'send_types',
        'supplier_id',
        'supplier_name',
        'recipient',

        // gestione anni
        'manage_year',
        'notify_year',
        'notify_month',

        // classificazione atto
        'act_type_id',
        'act_id',
        'act_year',
        'act_date',
        'act_attachment_path',
        'act_attachment_date',

        // utente inserimento spedizione
        'shipment_insert_user_id',
        'shipment_insert_date',

        // lavorazione e notifica
        'notify_date',
        'notify_attachment_path',
        'notify_attachment_date',
        'order_rif',
        'list_rif',
        'receive_protocol_number',
        'receive_protocol_date',
        'notify_amount',
        'amount_registration_date',

        // utente inserimento notifica
        'notify_insert_user_id',
        'notify_insert_date',

        // gestione spese
        'expense_type',
        'passive_invoice_id',
        'notify_expense_amount',
        'mark_expense_amount',
        'reinvoice',
        'reinvoice_type',
        'shipment_doc_type',
        'shipment_doc_number',
        'shipment_doc_date',
        'iban',

        // utente inserimento spese
        'expense_insert_user_id',
        'expense_insert_date',

        // pagamenti
        'payed',
        'payment_date',
        'payment_total',

        // utente inserimento pagamento
        'payment_insert_user_id',
        'payment_insert_date',

        // rifatturazione
        'reinvoice_id',
        'reinvoice_number',
        'reinvoice_date',
        'reinvoice_amount',

        // utente inserimento rifatturazione
        'reinvoice_insert_user_id',
        'reinvoice_insert_date',

        // allegati e registrazione
        'reinvoice_attachment_path',
        'reinvoice_attachment_date',
        'notify_date_registration_date',

        // utente registrazione
        'reinvoice_registration_user_id',
        'reinvoice_registration_date',

        // note
        'note',
    ];

    protected $casts = [
        // enum
        'notify_type' => NotifyType::class,
        'tax_type' => TaxType::class,
        'expense_type' => ExpenseType::class,
        'shipment_doc_type' => ShipmentDocType::class,
        'notify_month' => Month::class,
        'reinvoice_type' => ReinvoiceType::class,

        // date
        'send_protocol_date' => 'date',
        'act_date' => 'date',
        'act_attachment_date' => 'date',
        'shipment_insert_date' => 'date',
        'notify_date' => 'date',
        'notify_attachment_date' => 'date',
        'receive_protocol_date' => 'date',
        'amount_registration_date' => 'date',
        'notify_insert_date' => 'date',
        'expense_insert_date' => 'date',
        'payment_date' => 'date',
        'payment_insert_date' => 'date',
        'reinvoice_date' => 'date',
        'reinvoice_insert_date' => 'date',
        'reinvoice_attachment_date' => 'date',
        'notify_date_registration_date' => 'date',
        'reinvoice_registration_date' => 'date',
        'shipment_doc_date' => 'date',

        // decimali
        'notify_amount' => 'decimal:2',
        'notify_expense_amount' => 'decimal:2',
        'mark_expense_amount' => 'decimal:2',
        'payment_total' => 'decimal:2',
        'reinvoice_amount' => 'decimal:2',

        // bool
        'reinvoice' => 'boolean',
        'payed' => 'boolean',

        // interi (chiavi esterne)
        // 'company_id' => 'integer',
        'new_contract_id' => 'integer',
        'shipment_type_id' => 'integer',
        'client_id' => 'integer',
        'supplier_id' => 'integer',
        'act_type_id' => 'integer',
        'shipment_insert_user_id' => 'integer',
        'notify_insert_user_id' => 'integer',
        'passive_invoice_id' => 'integer',
        'expense_insert_user_id' => 'integer',
        'payment_insert_user_id' => 'integer',
        'reinvoice_id' => 'integer',
        'reinvoice_insert_user_id' => 'integer',
        'reinvoice_registration_user_id' => 'integer',

        // interi (anni)
        'manage_year' => 'integer',
        'notify_year' => 'integer',
        'act_year' => 'integer',

        // json
        'send_types' => 'json',
    ];

    public function getSendTypesAttribute($value)
    {
        $values = is_string($value) ? json_decode($value, true) : $value;
        if (!$values) return [];
        $accrualTypes = SendType::whereIn('id', $values)->pluck('name', 'id')->toArray();
        return array_map(function ($id) use ($accrualTypes) {
            return $accrualTypes[$id] ?? 'Sconosciuto';
        }, $values);
    }

    public function setSendTypesAttribute($values)
    {
        $this->attributes['send_types'] = json_encode($values);
    }

    // relazioni
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function shipmentType()
    {
        return $this->belongsTo(ShipmentType::class);
    }

    // public function sendType()
    // {
    //     return $this->belongsTo(SendType::class);
    // }

    public function actType()
    {
        return $this->belongsTo(ActType::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function contract()
    {
        return $this->belongsTo(NewContract::class, 'new_contract_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function passiveInvoice()
    {
        return $this->belongsTo(PassiveInvoice::class, 'passive_invoice_id');
    }

    public function reInvoice()
    {
        return $this->belongsTo(Invoice::class, 'reinvoice_id');
    }

    public function shipmentInsertUser()
    {
        return $this->belongsTo(User::class, 'shipment_insert_user_id');
    }

    public function notifyInsertUser()
    {
        return $this->belongsTo(User::class, 'notify_insert_user_id');
    }

    public function expenseInsertUser()
    {
        return $this->belongsTo(User::class, 'expense_insert_user_id');
    }

    public function paymentInsertUser()
    {
        return $this->belongsTo(User::class, 'payment_insert_user_id');
    }

    public function reinvoiceInsertUser()
    {
        return $this->belongsTo(User::class, 'reinvoice_insert_user_id');
    }

    public function reinvoiceRegistrationUser()
    {
        return $this->belongsTo(User::class, 'reinvoice_registration_user_id');
    }

    public function shipmentInserted()                                                  // funzione che controlla la presenza dell'inserimento dell'invio
    {
        return !is_null($this->shipment_insert_user_id) && !is_null($this->shipment_insert_date);
    }

    public function notificationInserted()                                              // funzione che controlla la presenza dell'inserimento della notifica
    {
        return $this->shipmentInserted() &&
               (!is_null($this->notify_insert_user_id) && !is_null($this->notify_insert_date));
    }

    public function expenseInserted()                                                   // funzione che controlla la presenza dell'inserimento delle spese
    {
        return $this->notificationInserted() &&
               (!is_null($this->expense_insert_user_id) && !is_null($this->expense_insert_date));
    }

    public function paymentInserted()                                                   // funzione che controlla la presenza dell'inserimento dei pagamenti
    {
        return $this->expenseInserted() &&
               (!is_null($this->payment_insert_user_id) && !is_null($this->payment_insert_date));
    }

    public function reinvoiceInserted()                                                 // funzione che controlla la presenza dell'inserimento della rifatturazione
    {
        return $this->paymentInserted() &&
               (!is_null($this->reinvoice_insert_user_id) && !is_null($this->reinvoice_insert_date));
    }

    public function reinvoiceRegistered()                                               // funzione che controlla la presenza della registrazione della rifatturazione
    {
        return $this->reinvoiceInserted() &&
               (!is_null($this->reinvoice_registration_user_id) && !is_null($this->reinvoice_registration_date));
    }

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->company_id = Filament::getTenant()?->id;
            $expense->shipment_insert_user_id = Auth::id();
            $expense->shipment_insert_date = today();
            $contract = NewContract::find($expense->new_contract_id);
            $expense->reinvoice = $contract->reinvoice ?? false;
            $expense->reinvoice_type = $contract->reinvoice_type ?? null;
            if ($expense->notify_type === NotifyType::MESSO) {
                $expense->shipment_doc_type = ShipmentDocType::MESSO;
            } elseif ($expense->notify_type === NotifyType::SPEDIZIONE) {
                $expense->shipment_doc_type = ShipmentDocType::SPEDIZIONE;
            }
        });

        static::created(function ($expense) {
            //
        });

        static::updating(function ($expense) {
            // $stages = [
            //     'reinvoiceInserted' => ['reinvoice_registration_user_id', 'reinvoice_registration_date'],
            //     'paymentInserted' => ['reinvoice_insert_user_id', 'reinvoice_insert_date'],
            //     'expenseInserted' => ['payment_insert_user_id', 'payment_insert_date'],
            //     'notificationInserted' => ['expense_insert_user_id', 'expense_insert_date'],
            //     'shipmentInserted' => ['notify_insert_user_id', 'notify_insert_date'],
            // ];
            // foreach ($stages as $method => [$userField, $dateField]) {
            //     if ($expense->$method()) {
            //         $expense->$userField = Auth::id();
            //         $expense->$dateField = today();
            //         break;
            //     }
            // }
            // avanzamento
            if ($expense->reinvoiceInserted() && ($expense->notify_date_registration_date || $expense->reinvoice_attachment_path)) {
                PostalExpense::withoutEvents(function () use ($expense) {
                    $expense->reinvoice_registration_user_id = Auth::id();
                    $expense->reinvoice_registration_date = today();
                });
            }
            else if($expense->paymentInserted() && $expense->reinvoice_id){
                PostalExpense::withoutEvents(function () use ($expense) {
                    $expense->reinvoice_insert_user_id = Auth::id();
                    $expense->reinvoice_insert_date = today();
                });
            }
            else if($expense->expenseInserted()){
                PostalExpense::withoutEvents(function () use ($expense) {
                    $expense->payment_insert_user_id = Auth::id();
                    $expense->payment_insert_date = today();
                });
            }
            else if($expense->notificationInserted()){
                PostalExpense::withoutEvents(function () use ($expense) {
                    $expense->expense_insert_user_id = Auth::id();
                    $expense->expense_insert_date = today();
                });
            }
            else if($expense->shipmentInserted()){
                PostalExpense::withoutEvents(function () use ($expense) {
                    $expense->notify_insert_user_id = Auth::id();
                    $expense->notify_insert_date = today();
                });
            }
            // Aggiornamento allegati
            if ($expense->isDirty('notify_attachment_path')) {
                $oldPath = $expense->getOriginal('notify_attachment_path');
                if ($oldPath) {
                    $disk = config('filesystems.default');
                    Storage::disk($disk)->delete($oldPath);
                }
            }
            // dd(!array($expense->notify_attachment_path));
            if (!array($expense->notify_attachment_path)){
                $expense->notify_attachment_path[0] = $expense->notify_attachment_path;
            }
            // dd($expense->notify_attachment_path);
            // Gestione merge PDF multipli anche in update
            if (is_array($expense->notify_attachment_path) && count($expense->notify_attachment_path) >= 1) {
                $processedPath = self::mergePdfFiles($expense->notify_attachment_path, $expense);

                $oldPath = $expense->getOriginal('notify_attachment_path');

                if ($oldPath && !is_array($oldPath) && $oldPath !== $processedPath) {
                    $disk = config('filesystems.default');
                    Storage::disk($disk)->delete($oldPath);
                }

                $expense->notify_attachment_path = $processedPath;
            }
            // creazione voce fattura
            if($expense->reinvoice_id && !$expense->reinvoice_registration_user_id){
                // $amount = ($expense->notify_amount ?? 0) + ($expense->notify_expense_amount ?? 0) + ($expense->mark_expense_amount ?? 0);
                $amount = ($expense->notify_amount ?? 0) + ($expense->mark_expense_amount ?? 0);
                $checkInvoiceItems = InvoiceItem::where('postal_expense_id', $expense->id)->first();
                if(!$checkInvoiceItems){
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $expense->reinvoice_id,
                        // 'description' => 'Spese di notifica da ' . ($expense->supplier_id ? $expense->supplier->denomination : $expense->supplier),
                        'description' => 'Rimborsi escl.Art. 15 ex D.P.R. 633/72',
                        'amount' => $amount,
                        'total' => $amount,
                        'vat_code_type' => VatCodeType::VC06,
                        'auto' => false,
                        'postal_expense_id' => $expense->id
                    ]);

                    $invoiceItem->calculateTotal();
                    $invoiceItem->save();
                    $invoiceItem->checkStampDuty();
                    $invoiceItem->autoInsert();

                    PostalExpense::withoutEvents(function () use ($expense, $amount) {
                            $expense->update([
                                'reinvoice_amount' => $amount,
                            ]);
                        });
                }
            }
        });

        static::saving(function ($expense) {
            if (is_array($expense->notify_attachment_path)) {
                $disk = Storage::disk(config('filesystems.default'));
                $cleanPaths = [];

                foreach ($expense->notify_attachment_path as $path) {
                    if (!empty($path) && $disk->exists($path)) {
                        $cleanPaths[] = $path;
                    } else {
                        \Log::warning("File rimosso perché non esiste più", ['path' => $path]);
                    }
                }

                if (empty($cleanPaths)) {
                    $expense->notify_attachment_path = null;
                } elseif (count($cleanPaths) === 1) {
                    $expense->notify_attachment_path = $cleanPaths[0];
                } else {
                    $expense->notify_attachment_path = $cleanPaths; // array con più file → verrà mergiato dopo
                }
            }
        });

        static::saved(function ($expense) {
            // if (!is_array($expense->notify_attachment_path) || count($expense->notify_attachment_path) <= 1) {
            //     return;
            // }

            // $paths = array_filter($expense->notify_attachment_path);

            // $mergedPath = self::mergeNotifyPdfs($paths, $expense);

            // if ($mergedPath) {
            //     PostalExpense::withoutEvents(function () use ($expense, $mergedPath) {
            //         $expense->update(['notify_attachment_path' => $mergedPath]);
            //     });

            //     // Elimina i vecchi file
            //     foreach ($paths as $path) {
            //         if ($path !== $mergedPath) {
            //             Storage::disk('local')->delete($path);
            //         }
            //     }
            // }

            $existA = Attachment::where('attachment_type', 'postal_act')->where('element_id', $expense->id)->first();
            if ($expense->act_attachment_path) {
                $filenameAct = basename($expense->act_attachment_path) ?: 'unknown';
                $dataA = [
                    'company_id' => Filament::getTenant()->id,
                    'client_id' => $expense->client_id,
                    'contract_id' => $expense->new_contract_id,
                    'element_id' => $expense->id,
                    'attachment_type' => 'postal_act',
                    'attachment_filename' => $filenameAct,
                    'attachment_date' => $expense->act_date,
                    'attachment_upload_date' => now()->toDateString(),
                    'attachment_path' => $expense->act_attachment_path,
                ];
                if (!$existA) { $actAttachment = Attachment::create($dataA); }
                else { $existA->update($dataA); }
            } elseif ($existA) { $existA->delete(); }

            $existN = Attachment::where('attachment_type', 'postal_notify')->where('element_id', $expense->id)->first();
            if ($expense->notify_attachment_path) {
                $filenameAct = basename($expense->notify_attachment_path) ?: 'unknown';
                $dataN = [
                    'company_id' => Filament::getTenant()->id,
                    'client_id' => $expense->client_id,
                    'contract_id' => $expense->new_contract_id,
                    'element_id' => $expense->id,
                    'attachment_type' => 'postal_notify',
                    'attachment_filename' => $filenameAct,
                    'attachment_date' => $expense->notify_date,
                    'attachment_upload_date' => now()->toDateString(),
                    'attachment_path' => $expense->notify_attachment_path,
                ];
                if (!$existN) { $actAttachment = Attachment::create($dataN); }
                else { $existN->update($dataN); }
            } elseif ($existN) { $existN->delete(); }

            $existR = Attachment::where('attachment_type', 'postal_reinvoice')->where('element_id', $expense->id)->first();
            if ($expense->reinvoice_attachment_path) {
                $filenameAct = basename($expense->reinvoice_attachment_path) ?: 'unknown';
                $dataR = [
                    'company_id' => Filament::getTenant()->id,
                    'client_id' => $expense->client_id,
                    'contract_id' => $expense->new_contract_id,
                    'element_id' => $expense->id,
                    'attachment_type' => 'postal_reinvoice',
                    'attachment_filename' => $filenameAct,
                    'attachment_date' => $expense->reinvoice_date,
                    'attachment_upload_date' => now()->toDateString(),
                    'attachment_path' => $expense->reinvoice_attachment_path,
                ];
                if (!$existR) { $actAttachment = Attachment::create($dataR); }
                else { $existR->update($dataN); }
            } elseif ($existR) { $existR->delete(); }
        });

        static::deleting(function ($expense) {
            //
        });

        static::deleted(function ($expense) {
            $existAct = Attachment::where('attachment_type', 'postal_act')->where('element_id', $expense->id)->first();                // elimino l'allegato dell'atto notificato
            if ($existAct) { $existAct->delete(); }

            $existNotify = Attachment::where('attachment_type', 'postal_notify')->where('element_id', $expense->id)->first();          // elimino l'allegato della notifica
            if ($existNotify) { $existNotify->delete(); }

            $existReinvoice = Attachment::where('attachment_type', 'postal_reinvoice')->where('element_id', $expense->id)->first();    // elemino l'allegato della fattura emessa
            if ($existReinvoice) { $existReinvoice->delete(); }
        });

    }

    /**
     * Unisce più file PDF in un unico file usando FPDI
     */
    // private static function mergePdfFilesA(array $paths, PostalExpense $expense): ?string
    // {
    //     try {
    //         $disk = Storage::disk(config('filesystems.default'));
    //         $pdf = new \setasign\Fpdi\Fpdi();

    //         \Log::info('=== INIZIO MERGE PDF ===', ['paths' => $paths]);

    //         foreach ($paths as $path) {
    //             if (!$disk->exists($path)) {
    //                 \Log::warning("File non trovato: {$path}");
    //                 continue;
    //             }

    //             $fullPath = $disk->path($path);
    //             \Log::info("Aggiungo al merge: {$fullPath}");

    //             $pageCount = $pdf->setSourceFile($fullPath);

    //             for ($i = 1; $i <= $pageCount; $i++) {
    //                 $tplIdx = $pdf->importPage($i);
    //                 $size = $pdf->getTemplateSize($tplIdx);

    //                 $pdf->AddPage(
    //                     $size['width'] > $size['height'] ? 'L' : 'P',
    //                     [$size['width'], $size['height']]
    //                 );
    //                 $pdf->useTemplate($tplIdx);
    //             }

    //             $processedFiles[] = $path;
    //         }

    //         if (empty($processedFiles)) {
    //             \Log::warning("Nessun file valido da unire");
    //             return reset($paths) ?? null;
    //         }

    //         // Nome file finale
    //         $date = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    //         $shipmentType = $expense->shipmentType->name ?? 'modalita';
    //         $client = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
    //         $taxType = $expense->taxType?->getLabel() ?? '';
    //         $actType = $expense->actType?->name ?? 'tipo';
    //         $rifOrder = $expense->order_rif;
    //         $amount = ($record->notify_amount ?? 0);
    //         $fileName = sprintf('%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.%s', $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $amount, 'pdf');

    //         $finalPath = "reg_post_richiesta/{$fileName}";

    //         // Crea directory se non esiste
    //         $disk->makeDirectory('reg_post_richiesta', 0755, true, true);

    //         $fullFinalPath = $disk->path($finalPath);
    //         $pdf->Output('F', $fullFinalPath);

    //         \Log::info("✅ Merge completato con successo", ['path' => $finalPath]);

    //         foreach ($processedFiles as $path) {
    //             if ($path !== $finalPath) {
    //                 $disk->delete($path);
    //                 \Log::info("File eliminato: {$path}");
    //             }
    //         }

    //         return $finalPath;

    //     } catch (\Exception $e) {
    //         \Log::error("Errore merge PDF: " . $e->getMessage());
    //         \Log::error($e->getTraceAsString());
    //         return $paths[0] ?? null;
    //     }
    // }

    // private static function mergePdfFilesB(array $paths, PostalExpense $expense): ?string
    // {
    //     if (count($paths) <= 1) {
    //         return reset($paths) ?: null;
    //     }

    //     try {
    //         $disk = Storage::disk(config('filesystems.default'));
    //         $pdf = new Fpdi();   // Usa la versione TCPDF di FPDI

    //         $processedCount = 0;
    //         $processedFiles = []; // AGGIUNGI QUESTA RIGA - era mancante!

    //         \Log::info('=== INIZIO MERGE PDF CON TCPDF ===', ['paths' => $paths]);

    //         foreach ($paths as $path) {
    //             if (empty($path) || !$disk->exists($path)) {
    //                 \Log::warning("File non trovato per il merge", ['path' => $path]);
    //                 continue;
    //             }

    //             $fullPath = $disk->path($path);

    //             try {
    //                 $pageCount = $pdf->setSourceFile($fullPath);
    //                 \Log::info("Importo {$pageCount} pagine da: {$path}");

    //                 for ($i = 1; $i <= $pageCount; $i++) {
    //                     $tplIdx = $pdf->importPage($i);
    //                     $size = $pdf->getTemplateSize($tplIdx);

    //                     $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
    //                     $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    //                     $pdf->useTemplate($tplIdx);
    //                 }

    //                 $processedFiles[] = $path;
    //                 $processedCount++;
    //                 \Log::info("✅ File importato con successo: {$path}");

    //             } catch (\Exception $e) {
    //                 \Log::error("❌ Impossibile importare il file: {$path}", [
    //                     'error' => $e->getMessage(),
    //                     'trace' => $e->getTraceAsString()
    //                 ]);
    //                 continue;
    //             }
    //         }

    //         if ($processedCount === 0) {
    //             \Log::error('❌ Nessun PDF valido da mergiare');
    //             return reset($paths) ?? null;
    //         }

    //         if ($processedCount === 1) {
    //             \Log::info('Solo un file valido, restituisco quello senza merge');
    //             return $processedFiles[0];
    //         }

    //         // Nome file finale - CORRETTO
    //         $date = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    //         $shipmentType = Str::slug($expense->shipmentType->name ?? 'modalita', '_');
    //         $client = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
    //         $taxType = Str::slug($expense->tax_type?->getLabel() ?? 'tax', '_'); // CORRETTO: tax_type invece di taxType
    //         $actType = Str::slug($expense->actType?->name ?? 'tipo', '_');
    //         $rifOrder = Str::slug($expense->order_rif ?? 'rif', '_');
    //         $amount = number_format($expense->notify_amount ?? 0, 2, '_', ''); // CORRETTO: $expense invece di $record

    //         $fileName = sprintf(
    //             '%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.pdf',
    //             $date,
    //             $shipmentType,
    //             $client,
    //             $taxType,
    //             $actType,
    //             $rifOrder,
    //             $amount
    //         );

    //         $finalPath = "reg_post_richiesta/{$fileName}";

    //         // Crea directory se non esiste
    //         if (!$disk->exists('reg_post_richiesta')) {
    //             $disk->makeDirectory('reg_post_richiesta', 0755, true);
    //         }

    //         $fullFinalPath = $disk->path($finalPath);
    //         $pdf->Output($fullFinalPath, 'F');

    //         \Log::info('✅ Merge PDF completato con FPDI+TCPDF', [
    //             'path' => $finalPath,
    //             'full_path' => $fullFinalPath,
    //             'files_processed' => $processedCount,
    //             'file_size' => file_exists($fullFinalPath) ? filesize($fullFinalPath) : 'N/A'
    //         ]);

    //         // Verifica che il file sia stato creato
    //         if (!file_exists($fullFinalPath) || filesize($fullFinalPath) === 0) {
    //             \Log::error('❌ Il file merged non è stato creato o è vuoto');
    //             return reset($processedFiles) ?? null;
    //         }

    //         // Elimina i file temporanei
    //         foreach ($processedFiles as $path) {
    //             if ($path !== $finalPath && $disk->exists($path)) {
    //                 $disk->delete($path);
    //                 \Log::info("File temporaneo eliminato: {$path}");
    //             }
    //         }

    //         return $finalPath;

    //     } catch (\Exception $e) {
    //         \Log::error('❌ Errore critico merge PDF con TCPDF', [
    //             'message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return reset($paths) ?? null;
    //     }
    // }

    /**
     * Unisce (se necessario) e rinomina sempre il file PDF con nome standard
     */
    // private static function mergePdfFilesC(array $paths, PostalExpense $expense): ?string
    // {
    //     if (empty($paths)) {
    //         return null;
    //     }

    //     try {
    //         $disk = Storage::disk(config('filesystems.default'));
    //         $pdf = new Fpdi();
    //         $processedFiles = [];
    //         $processedCount = 0;

    //         \Log::info('=== INIZIO PROCESSAMENTO PDF ===', ['paths' => $paths]);

    //         foreach ($paths as $path) {
    //             if (empty($path) || !$disk->exists($path)) {
    //                 \Log::warning("File non trovato", ['path' => $path]);
    //                 continue;
    //             }

    //             $fullPath = $disk->path($path);

    //             try {
    //                 $pageCount = $pdf->setSourceFile($fullPath);
    //                 \Log::info("Importo {$pageCount} pagine da: {$path}");

    //                 for ($i = 1; $i <= $pageCount; $i++) {
    //                     $tplIdx = $pdf->importPage($i);
    //                     $size = $pdf->getTemplateSize($tplIdx);
    //                     $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
    //                     $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    //                     $pdf->useTemplate($tplIdx);
    //                 }

    //                 $processedFiles[] = $path;
    //                 $processedCount++;
    //                 \Log::info("✅ File importato con successo: {$path}");

    //             } catch (\Exception $e) {
    //                 \Log::error("❌ Impossibile importare il file: {$path}", ['error' => $e->getMessage()]);
    //                 continue;
    //             }
    //         }

    //         if ($processedCount === 0) {
    //             \Log::error('Nessun PDF valido');
    //             return reset($paths) ?? null;
    //         }

    //         // ====================== CREAZIONE NOME STANDARD ======================
    //         $date = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    //         $shipmentType = Str::slug($expense->shipmentType->name ?? 'modalita', '_');
    //         $client = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
    //         $taxType = Str::slug($expense->tax_type?->getLabel() ?? 'tax', '_');
    //         $actType = Str::slug($expense->actType?->name ?? 'tipo', '_');
    //         $rifOrder = Str::slug($expense->order_rif ?? 'rif', '_');
    //         $amount = number_format($expense->notify_amount ?? 0, 2, '', '');

    //         $fileName = sprintf(
    //             '%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.pdf',
    //             $date,
    //             $shipmentType,
    //             $client,
    //             $taxType,
    //             $actType,
    //             $rifOrder,
    //             $amount
    //         );

    //         $finalPath = "reg_post_richiesta/{$fileName}";
    //         $fullFinalPath = $disk->path($finalPath);

    //         // Crea cartella se non esiste
    //         $disk->makeDirectory('reg_post_richiesta', 0755, true, true);

    //         // Salva il file (merged o singolo rinominato)
    //         $pdf->Output($fullFinalPath, 'F');

    //         \Log::info('✅ PDF processato e rinominato con successo', [
    //             'final_path' => $finalPath,
    //             'files_processed' => $processedCount
    //         ]);

    //         // Elimina i file originali (tranne quello finale)
    //         foreach ($processedFiles as $oldPath) {
    //             if ($oldPath !== $finalPath && $disk->exists($oldPath)) {
    //                 $disk->delete($oldPath);
    //                 \Log::info("File temporaneo eliminato: {$oldPath}");
    //             }
    //         }

    //         return $finalPath;

    //     } catch (\Exception $e) {
    //         \Log::error('Errore critico nel processamento PDF', [
    //             'message' => $e->getMessage()
    //         ]);
    //         return reset($paths) ?? null;
    //     }
    // }

    /**
     * Processa, unisce (se necessario) e rinomina sempre il PDF
     * Gestisce anche file vecchi non esistenti
     */
    // private static function mergePdfFilesD(array $paths, PostalExpense $expense): ?string
    // {
    //     if (empty($paths)) {
    //         return null;
    //     }

    //     $disk = Storage::disk(config('filesystems.default'));

    //     // ====================== PULIZIA DEI PATH ======================
    //     $validPaths = [];
    //     foreach ($paths as $path) {
    //         if (empty($path)) continue;

    //         // Se il file esiste → lo teniamo
    //         if ($disk->exists($path)) {
    //             $validPaths[] = $path;
    //         } else {
    //             \Log::warning("File non esistente, verrà ignorato", ['path' => $path]);
    //         }
    //     }

    //     if (empty($validPaths)) {
    //         \Log::error('Nessun file valido dopo pulizia');
    //         return null;
    //     }

    //     try {
    //         $pdf = new Fpdi();
    //         $processedCount = 0;

    //         \Log::info('=== INIZIO PROCESSAMENTO PDF ===', ['valid_paths' => $validPaths]);

    //         foreach ($validPaths as $path) {
    //             $fullPath = $disk->path($path);

    //             try {
    //                 $pageCount = $pdf->setSourceFile($fullPath);
    //                 \Log::info("Importo {$pageCount} pagine da: {$path}");

    //                 for ($i = 1; $i <= $pageCount; $i++) {
    //                     $tplIdx = $pdf->importPage($i);
    //                     $size = $pdf->getTemplateSize($tplIdx);
    //                     $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
    //                     $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    //                     $pdf->useTemplate($tplIdx);
    //                 }

    //                 $processedCount++;
    //             } catch (\Exception $e) {
    //                 \Log::error("❌ Impossibile importare {$path}", ['error' => $e->getMessage()]);
    //                 continue;
    //             }
    //         }

    //         if ($processedCount === 0) {
    //             return reset($validPaths);
    //         }

    //         // ====================== GENERA NOME STANDARD ======================
    //         $date = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    //         $shipmentType = Str::slug($expense->shipmentType->name ?? 'modalita', '_');
    //         $client = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
    //         $taxType = Str::slug($expense->tax_type?->getLabel() ?? 'tax', '_');
    //         $actType = Str::slug($expense->actType?->name ?? 'tipo', '_');
    //         $rifOrder = Str::slug($expense->order_rif ?? 'rif', '_');
    //         $amount = number_format($expense->notify_amount ?? 0, 2, '', '');

    //         $fileName = sprintf(
    //             '%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.pdf',
    //             $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $amount
    //         );

    //         $finalPath = "reg_post_richiesta/{$fileName}";
    //         $fullFinalPath = $disk->path($finalPath);

    //         $disk->makeDirectory('reg_post_richiesta', 0755, true, true);

    //         $pdf->Output($fullFinalPath, 'F');

    //         \Log::info('✅ PDF processato con successo', [
    //             'final_path' => $finalPath,
    //             'files_processed' => $processedCount
    //         ]);

    //         // Elimina i vecchi file (tranne il nuovo)
    //         foreach ($validPaths as $oldPath) {
    //             if ($oldPath !== $finalPath && $disk->exists($oldPath)) {
    //                 $disk->delete($oldPath);
    //             }
    //         }

    //         return $finalPath;

    //     } catch (\Exception $e) {
    //         \Log::error('Errore critico merge PDF', ['message' => $e->getMessage()]);
    //         return reset($validPaths) ?? null;
    //     }
    // }

    /**
     * Processa SEMPRE il file (rinomina anche se è uno solo)
     * Gestisce sostituzione, merge e pulizia
     */
    // private static function mergePdfFilesE(array $paths, PostalExpense $expense): ?string
    // {
    //     if (empty($paths)) {
    //         return null;
    //     }

    //     $disk = Storage::disk(config('filesystems.default'));
    //     $validPaths = [];

    //     // Pulizia file non esistenti
    //     foreach ($paths as $path) {
    //         if (!empty($path) && $disk->exists($path)) {
    //             $validPaths[] = $path;
    //         } else {
    //             \Log::warning("File ignorato perché non esiste", ['path' => $path]);
    //         }
    //     }

    //     if (empty($validPaths)) {
    //         return null;
    //     }

    //     try {
    //         $pdf = new Fpdi();
    //         $processedCount = 0;

    //         \Log::info('=== PROCESSAMENTO PDF ===', ['valid_paths_count' => count($validPaths)]);

    //         foreach ($validPaths as $path) {
    //             $fullPath = $disk->path($path);

    //             try {
    //                 $pageCount = $pdf->setSourceFile($fullPath);

    //                 for ($i = 1; $i <= $pageCount; $i++) {
    //                     $tplIdx = $pdf->importPage($i);
    //                     $size = $pdf->getTemplateSize($tplIdx);
    //                     $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
    //                     $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    //                     $pdf->useTemplate($tplIdx);
    //                 }

    //                 $processedCount++;
    //             } catch (\Exception $e) {
    //                 \Log::error("Errore importazione file {$path}", ['error' => $e->getMessage()]);
    //                 continue;
    //             }
    //         }

    //         if ($processedCount === 0) {
    //             return reset($validPaths);
    //         }

    //         // ====================== GENERAZIONE NOME STANDARD (sempre) ======================
    //         $date = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    //         $shipmentType = Str::slug($expense->shipmentType->name ?? 'modalita', '_');
    //         $client = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
    //         $taxType = Str::slug($expense->tax_type?->getLabel() ?? 'tax', '_');
    //         $actType = Str::slug($expense->actType?->name ?? 'tipo', '_');
    //         $rifOrder = Str::slug($expense->order_rif ?? 'rif', '_');
    //         $amount = number_format($expense->notify_amount ?? 0, 2, '', '');

    //         $fileName = sprintf(
    //             '%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.pdf',
    //             $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $amount
    //         );

    //         $finalPath = "reg_post_richiesta/{$fileName}";
    //         $fullFinalPath = $disk->path($finalPath);

    //         $disk->makeDirectory('reg_post_richiesta', 0755, true, true);

    //         $pdf->Output($fullFinalPath, 'F');

    //         \Log::info('✅ PDF rinominato con successo', [
    //             'final_path' => $finalPath,
    //             'was_merge' => count($validPaths) > 1
    //         ]);

    //         // Elimina i vecchi file
    //         foreach ($validPaths as $oldPath) {
    //             if ($oldPath !== $finalPath && $disk->exists($oldPath)) {
    //                 $disk->delete($oldPath);
    //             }
    //         }

    //         return $finalPath;

    //     } catch (\Exception $e) {
    //         \Log::error('Errore merge/rinomina PDF', ['message' => $e->getMessage()]);
    //         return reset($validPaths) ?? null;
    //     }
    // }

    // private static function mergePdfFilesF(array $paths, PostalExpense $expense): ?string
    // {
    //     if (empty($paths)) {
    //         return null;
    //     }

    //     $disk = Storage::disk(config('filesystems.default'));

    //     // Ottieni il vecchio path per eliminarlo dopo
    //     $oldPath = $expense->getOriginal('notify_attachment_path');

    //     $validPaths = [];
    //     // Pulizia file non esistenti
    //     foreach ($paths as $path) {
    //         if (!empty($path) && $disk->exists($path)) {
    //             $validPaths[] = $path;
    //         } else {
    //             \Log::warning("File ignorato perché non esiste", ['path' => $path]);
    //         }
    //     }

    //     if (empty($validPaths)) {
    //         return null;
    //     }

    //     try {
    //         $pdf = new Fpdi();
    //         $processedCount = 0;

    //         \Log::info('=== PROCESSAMENTO PDF ===', [
    //             'valid_paths_count' => count($validPaths),
    //             'old_path' => $oldPath
    //         ]);

    //         foreach ($validPaths as $path) {
    //             $fullPath = $disk->path($path);

    //             try {
    //                 $pageCount = $pdf->setSourceFile($fullPath);
    //                 \Log::info("Importo {$pageCount} pagine da: {$path}");

    //                 for ($i = 1; $i <= $pageCount; $i++) {
    //                     $tplIdx = $pdf->importPage($i);
    //                     $size = $pdf->getTemplateSize($tplIdx);
    //                     $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
    //                     $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    //                     $pdf->useTemplate($tplIdx);
    //                 }

    //                 $processedCount++;
    //                 \Log::info("✅ File processato: {$path}");

    //             } catch (\Exception $e) {
    //                 \Log::error("❌ Errore importazione file {$path}", [
    //                     'error' => $e->getMessage()
    //                 ]);
    //                 continue;
    //             }
    //         }

    //         if ($processedCount === 0) {
    //             \Log::error('Nessun file processato con successo');
    //             return reset($validPaths);
    //         }

    //         // ====================== GENERAZIONE NOME STANDARD ======================
    //         $date = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
    //         $shipmentType = Str::slug($expense->shipmentType->name ?? 'modalita', '_');
    //         $client = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
    //         $taxType = Str::slug($expense->tax_type?->getLabel() ?? 'tax', '_');
    //         $actType = Str::slug($expense->actType?->name ?? 'tipo', '_');
    //         $rifOrder = Str::slug($expense->order_rif ?? 'rif', '_');
    //         $amount = number_format($expense->notify_amount ?? 0, 2, '', '');

    //         $fileName = sprintf(
    //             '%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.pdf',
    //             $date, $shipmentType, $client, $taxType, $actType, $rifOrder, $amount
    //         );

    //         $finalPath = "reg_post_richiesta/{$fileName}";
    //         $fullFinalPath = $disk->path($finalPath);

    //         // Crea directory se non esiste
    //         if (!$disk->exists('reg_post_richiesta')) {
    //             $disk->makeDirectory('reg_post_richiesta', 0755, true);
    //         }

    //         // Salva il PDF con il nome standard
    //         $pdf->Output($fullFinalPath, 'F');

    //         \Log::info('✅ PDF salvato con successo', [
    //             'final_path' => $finalPath,
    //             'full_path' => $fullFinalPath,
    //             'was_merge' => count($validPaths) > 1,
    //             'files_merged' => $processedCount,
    //             'file_exists' => file_exists($fullFinalPath),
    //             'file_size' => file_exists($fullFinalPath) ? filesize($fullFinalPath) : 'N/A'
    //         ]);

    //         // Verifica che il file sia stato creato
    //         if (!file_exists($fullFinalPath) || filesize($fullFinalPath) === 0) {
    //             \Log::error('❌ Il file finale non è stato creato o è vuoto');
    //             return reset($validPaths);
    //         }

    //         // Elimina i file temporanei caricati dall'utente
    //         foreach ($validPaths as $tempPath) {
    //             if ($tempPath !== $finalPath && $disk->exists($tempPath)) {
    //                 $disk->delete($tempPath);
    //                 \Log::info("File temporaneo eliminato: {$tempPath}");
    //             }
    //         }

    //         // Elimina il vecchio file merged se esiste e non è lo stesso del nuovo
    //         if ($oldPath && !is_array($oldPath) && $oldPath !== $finalPath && $disk->exists($oldPath)) {
    //             $disk->delete($oldPath);
    //             \Log::info("File vecchio merged eliminato: {$oldPath}");
    //         }

    //         return $finalPath;

    //     } catch (\Exception $e) {
    //         \Log::error('❌ Errore critico merge/rinomina PDF', [
    //             'message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return reset($validPaths) ?? null;
    //     }
    // }

    /**
     * Processa SEMPRE i PDF (rinomina anche singolo file)
     * Gestisce sostituzione di merged → singolo e viceversa
     */
    private static function mergePdfFiles(array $paths, PostalExpense $expense): ?string
    {
        if (empty($paths)) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $validPaths = [];

        // Pulizia rigorosa
        foreach ($paths as $path) {
            if (!empty($path) && $disk->exists($path)) {
                $validPaths[] = $path;
            } else {
                \Log::warning("File ignorato (non esiste)", ['path' => $path]);
            }
        }

        if (empty($validPaths)) {
            \Log::warning('Nessun file valido dopo pulizia');
            return null;
        }

        try {
            $pdf = new Fpdi();
            $processedCount = 0;

            \Log::info('=== PROCESSAMENTO PDF INIZIATO ===', [
                'input_count' => count($paths),
                'valid_count' => count($validPaths)
            ]);

            foreach ($validPaths as $path) {
                $fullPath = $disk->path($path);

                try {
                    $pageCount = $pdf->setSourceFile($fullPath);
                    \Log::info("→ Importando {$pageCount} pagine da: {$path}");

                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tplIdx = $pdf->importPage($i);
                        $size = $pdf->getTemplateSize($tplIdx);
                        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplIdx);
                    }
                    $processedCount++;
                } catch (\Exception $e) {
                    \Log::error("Errore importazione {$path}", ['error' => $e->getMessage()]);
                    continue;
                }
            }

            if ($processedCount === 0) {
                return reset($validPaths);
            }

            // ====================== NOME STANDARD (sempre applicato) ======================
            $date       = $expense->receive_protocol_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $shipment   = Str::slug($expense->shipmentType->name ?? 'modalita', '_');
            $client     = Str::slug(optional($expense->client)->denomination ?? 'cliente', '_');
            $taxType    = Str::slug($expense->tax_type?->getLabel() ?? 'tax', '_');
            $actType    = Str::slug($expense->actType?->name ?? 'tipo', '_');
            $rifOrder   = Str::slug($expense->order_rif ?? 'rif', '_');
            $amount     = number_format($expense->notify_amount ?? 0, 2, '', '');

            $fileName = sprintf(
                '%s_REG-POST-RICHIESTA_%s_%s_%s_%s_%s_%s.pdf',
                $date, $shipment, $client, $taxType, $actType, $rifOrder, $amount
            );

            $finalPath = "reg_post_richiesta/{$fileName}";
            $fullFinalPath = $disk->path($finalPath);

            $disk->makeDirectory('reg_post_richiesta', 0755, true, true);

            $pdf->Output($fullFinalPath, 'F');

            \Log::info('✅ PDF salvato e rinominato', [
                'final_path' => $finalPath,
                'was_single' => count($validPaths) === 1
            ]);

            // Pulizia vecchi file
            foreach ($validPaths as $oldPath) {
                if ($oldPath !== $finalPath && $disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $finalPath;

        } catch (\Exception $e) {
            \Log::error('Errore critico in mergePdfFiles', ['message' => $e->getMessage()]);
            return reset($validPaths) ?? null;
        }
    }
}
