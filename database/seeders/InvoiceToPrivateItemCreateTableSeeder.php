<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceToPrivateItemCreateTableSeeder extends Seeder
{
    /**
     * Procedura di aggiornamento delle fatture a privati importate dal vecchio programma (creazione voci fattura)
     */
    public function run(): void
    {
        DB::beginTransaction();
        Log::info('Inizio creazione voci fatture con IVA a privati.');
        $i = 0;
        try{
            // $invoices = Invoice::whereNull('contract_id')->get();                                   // tutte le fatture senza contratto (verso privati)
            // $invoices = Invoice::whereNull('contract_id')->where('vat', '!=', 0)->get();            // fatture senza contratto (verso privati) con iva
            $invoices = Invoice::where('id', 5197)->get();                                          // fattura di test
            Log::info('Numero di fatture: ' . count($invoices));
            foreach($invoices as $invoice){
                $i++;
                Log::info($i . ') Creazione voci fattura (' . $invoice->id . ') ' . $invoice->getNewInvoiceNumber() . ' del ' . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'));
                $aV = [
                    'invoice_id' => $invoice->id,
                    'invoice_element_id' => null,
                    'description' => 'Importo',
                    'amount' => (string) $invoice->importo,
                    'vat_code_type' => 'vc01',
                    'total' => (string) $invoice->importo * 1.22,
                ];
                $itemV = InvoiceItem::create($aV);
                $itemV->calculateTotal();
                $itemV->save();
                $itemV->autoInsert();
                Log::info('Creata voce importo');
                if($invoice->spese > 0){
                    $aE = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => 8,
                        'description' => 'Spese',
                        'amount' => $invoice->spese,
                        'vat_code_type' => 'vc06',
                        'total' => $invoice->spese,
                    ];
                    $itemE = InvoiceItem::create($aE);
                    $itemE->calculateTotal();
                    $itemE->save();
                    $itemE->autoInsert();
                    Log::info('Creata voce spese');
                }
                if($invoice->ordinario > 0){
                    $aO = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => 9,
                        'description' => 'Importo esente IVA',
                        'amount' => $invoice->ordinario,
                        'vat_code_type' => 'vc06',
                        'total' => $invoice->ordinario,
                    ];
                    $itemO = InvoiceItem::create($aO);
                    $itemO->calculateTotal();
                    $itemO->save();
                    $itemO->autoInsert();
                    Log::info('Creata voce importo esente iva');
                }
                if($invoice->temporaneo > 0){
                    $aT = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Canone esente IVA ex art. 10 del DPR 633/72',
                        'amount' => $invoice->temporaneo,
                        'vat_code_type' => 'vc17',
                        'total' => $invoice->temporaneo,
                    ];
                    $itemT = InvoiceItem::create($aT);
                    $itemT->calculateTotal();
                    $itemT->save();
                    $itemT->autoInsert();
                    Log::info('Creata voce canone');
                }
                if($invoice->rimborsi > 0){
                    $aT = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Rimborso spese esente IVA ex art. 10 del DPR 633/72',
                        'amount' => $invoice->rimborsi,
                        'vat_code_type' => 'vc17',
                        'total' => $invoice->rimborsi,
                    ];
                    $itemT = InvoiceItem::create($aT);
                    $itemT->calculateTotal();
                    $itemT->save();
                    $itemT->autoInsert();
                    Log::info('Creata voce rimborso');
                }
                if($invoice->affissioni > 0){
                    $aT = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Cauzione escl.Art 15 ex DPR 633/72',
                        'amount' => $invoice->affissioni,
                        'vat_code_type' => 'vc06',
                        'total' => $invoice->affissioni,
                    ];
                    $itemT = InvoiceItem::create($aT);
                    $itemT->calculateTotal();
                    $itemT->save();
                    $itemT->autoInsert();
                    Log::info('Creata voce cauzione');
                }
                if($invoice->bollo > 0){
                    $aS = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Imposta di Bollo escl. Art. 15 ex DPR 633/72',
                        'amount' => $invoice->bollo,
                        'vat_code_type' => 'vc06a',
                        'total' => $invoice->bollo,
                    ];
                    $itemS = InvoiceItem::create($aS);
                    $itemS->calculateTotal();
                    $itemS->save();
                    Log::info('Creata voce bollo');
                }
            }

            Log::info('Completata creazione voci fatture ('. $i .') con IVA a privati.');

            DB::commit();

        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error('Errore: ' . $ex->getMessage() . ' - ' . $ex->getLine());
        }

    }
}
