<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Tender;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContainersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        // $a_results = DB::select(
        //     "SELECT T.id as tender_id, C.id, C.client_id, C.number, C.contract_date, T.cig_code, T.office_name, T.office_code, T.tax_type
        //     FROM `contracts` as C 
        //     JOIN invoices as INV ON 

        //     INV.client_id=C.client_id 
        //     AND INV.description LIKE CONCAT('%',  C.number,' del ', DATE_FORMAT(C.contract_date, '%d/%m/%Y'), '%')
        //     AND INV.tax_type=C.tax_type

        //     JOIN tenders as T ON T.id=INV.tender_id
        //     WHERE C.number != '' AND C.contract_date is not null
        //     GROUP BY T.id, C.id
        //     ORDER BY `INV`.`tender_id` ASC;"
        // );


        $tenders = Tender::select('invoices.tender_id')->addSelect('tenders.office_name')->addSelect('tenders.office_code')
            ->addSelect('tenders.type')->addSelect('tenders.cig_code')->addSelect('tenders.client_id')
            ->join('invoices', 'invoices.tender_id', 'tenders.id')
            ->groupBy('tenders.office_name')->groupBy('tenders.office_code')
            ->groupBy('tenders.type')->groupBy('tenders.cig_code')->groupBy('tenders.client_id')->get();
        foreach ($tenders as $row) {
            $name = $row->office_name . " (" . $row->office_code . ") - " . $row->type->getDescription();
            if ($row->cig_code != "")
                $name .= " - CIG " . $row->cig_code;

            $a_tenders = Tender::select('tax_type')->where('office_name', $row->office_name)
                ->where('office_code', $row->office_code)->where('type', $row->type)
                ->where('client_id', $row->client_id)->where('cig_code', $row->cig_code)
                ->groupBy('tax_type')->get();
            $insertTaxTypes = "";
            foreach ($a_tenders as $key => $a_tender) {
                if ($key > 0)
                    $insertTaxTypes .= ", ";
                $insertTaxTypes .= $a_tender->tax_type->value;
            }

            $query = "INSERT INTO containers (tender_id, client_id, name, tax_types, accrual_types) VALUE ({$row->tender_id}, {$row->client_id}, \"" . $name . "\", '" . $insertTaxTypes . "', 'ordinary, coercive')";
            // dd($query);
            DB::insert($query);
            $container_id = DB::getPdo()->lastInsertId();
            $a_tenders = Tender::select('id')->where('office_name', $row->office_name)
                ->where('office_code', $row->office_code)->where('type', $row->type)
                ->where('client_id', $row->client_id)->where('cig_code', $row->cig_code)
                ->get();
            foreach ($a_tenders as $key => $a_tender) {
                Invoice::where('tender_id', $a_tender->id)->update(['container_id'=>$container_id]);
                Tender::where('id', $a_tender->id)->update(['container_id'=>$container_id]);
            }
        }

        $a_contracts = DB::select(
                "SELECT INV.container_id, C.id
                FROM `contracts` as C 
                JOIN invoices as INV ON 
                INV.client_id=C.client_id 
                AND INV.description LIKE CONCAT('%',  C.number,' del ', DATE_FORMAT(C.contract_date, '%d/%m/%Y'), '%')
                AND INV.tax_type=C.tax_type
                GROUP BY C.id
                ORDER BY `C`.`id` ASC;"
            );

        foreach($a_contracts as $a_contract){
            Contract::where('id', $a_contract->id)->update(['container_id'=>$a_contract->container_id]);
        }
    }
}
