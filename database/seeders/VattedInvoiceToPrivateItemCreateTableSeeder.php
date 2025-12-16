<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VattedInvoiceToPrivateItemCreateTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        Log::info('Inizio creazione voci fatture con IVA a privati.');
        $i = 0;
        try{
            // $invoices = Invoice::whereNull('contract_id')->where('vat', '!=', 0)->limit(1)->get();
            $invoices = Invoice::where('id', 5197)->get();
            foreach($invoices as $invoice){
                $i++;
                Log::info($i . ') Creazione voci fattura (' . $invoice->id . ') ' . $invoice->getNewInvoiceNumber() . ' del ' . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y'));
                $aV = [
                    'invoice_id' => $invoice->id,
                    'invoice_element_id' => null,
                    'description' => 'Importo',
                    'amount' => (string) $invoice->importo,
                    'vat_code_type' => 'vc01',
                ];
                $itemV = InvoiceItem::create($aV);
                $itemV->calculateTotal();
                $itemV->save();
                $itemV->autoInsert();
                Log::info('Creata voce importo');
                if($invoice->spese > 0){
                    $aE = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Spese',
                        'amount' => $invoice->spese,
                        'vat_code_type' => 'vc06'
                    ];
                    $itemE = InvoiceItem::create($aE);
                    $itemE->save();
                    $itemE->autoInsert();
                    Log::info('Creata voce spese');
                }
                if($invoice->ordinario > 0){
                    $aO = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Importo esente IVA',
                        'amount' => $invoice->ordinario,
                        'vat_code_type' => 'vc06'
                    ];
                    $itemO = InvoiceItem::create($aO);
                    $itemO->save();
                    $itemO->autoInsert();
                    Log::info('Creata voce spese');
                }
                if($invoice->temporaneo > 0){
                    $aT = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Canone esente IVA ex art. 10 del DPR 633/72',
                        'amount' => $invoice->temporaneo,
                        'vat_code_type' => 'vc06'
                    ];
                    $itemT = InvoiceItem::create($aT);
                    $itemT->save();
                    $itemT->autoInsert();
                    Log::info('Creata voce canone');
                }
                if($invoice->bollo > 0){
                    $aS = [
                        'invoice_id' => $invoice->id,
                        'invoice_element_id' => null,
                        'description' => 'Imposta di Bollo escl. Art. 15 ex DPR 633/72',
                        'amount' => $invoice->bollo,
                        'vat_code_type' => 'vc06a'
                    ];
                    $itemS = InvoiceItem::create($aS);
                    $itemS->save();
                    $itemS->autoInsert();
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
