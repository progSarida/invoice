<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Tender;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $invoices = Invoice::select('invoices.*')->addSelect('tenders.type as tender_type')
            ->join('tenders','tenders.id','invoices.tender_id')
            ->get();

        foreach($invoices as $invoice){
            $paramsQuery = "SELECT * FROM invoiceamounts WHERE Tributo = '".$invoice->tax_type->value."' AND Gestione = '".$invoice->tender_type."'";
            $invoiceAmounts = DB::select($paramsQuery);
            
            
            foreach($invoiceAmounts as $item){
                if($item->Iva=="Y")
                    $is_with_vat = 1;
                else
                    $is_with_vat = 0;
                $name = $item->Name;
                if($name=="impostabollo")
                    $name = "bollo";
                $label = str_replace("a'","à",$item->Label);
                if($invoice->$name==0)
                    continue;
                
                $query = "INSERT INTO invoice_items (invoice_id, description, amount, is_with_vat) VALUE ({$invoice->id}, \"{$label}\", {$invoice->$name}, {$is_with_vat})";
                DB::insert($query);
            }
        }
    }
}
