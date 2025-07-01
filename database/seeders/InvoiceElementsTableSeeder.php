<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceElementsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('invoice_elements')->delete();

        DB::table('invoice_elements')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'name' => 'Rimborsi escl. Art. 15 ex DPR 633/72',
                'description' => NULL,
                'amount' => '2.00',
                'vat_code_type' => 'vc06',
                'created_at' => '2025-07-01 12:15:58',
                'updated_at' => '2025-07-01 12:15:58',
            ),
            1 =>
            array (
                'id' => 2,
                'company_id' => 1,
                'name' => 'CUP',
                'description' => NULL,
                'amount' => '0.00',
                'vat_code_type' => 'vc01',
                'created_at' => '2025-07-01 12:15:58',
                'updated_at' => '2025-07-01 12:15:58',
            ),
            2 =>
            array (
                'id' => 3,
                'company_id' => 1,
                'name' => 'IMU',
                'description' => NULL,
                'amount' => '0.00',
                'vat_code_type' => 'vc01',
                'created_at' => '2025-07-01 12:18:04',
                'updated_at' => '2025-07-01 12:18:04',
            ),
            3 =>
            array (
                'id' => 4,
                'company_id' => 1,
                'name' => 'OSAP',
                'description' => NULL,
                'amount' => '0.00',
                'vat_code_type' => 'vc01',
                'created_at' => '2025-07-01 12:18:04',
                'updated_at' => '2025-07-01 12:18:04',
            ),
        ));


    }
}
