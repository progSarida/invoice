<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FiscalProfilesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('fiscal_profiles')->delete();

        DB::table('fiscal_profiles')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'tax_regime' => 'rf01',
                'vat_enforce' => NULL,
                'vat_enforce_type' => NULL,
                'created_at' => '2025-07-03 08:03:23',
                'updated_at' => '2025-07-03 09:55:19',
            ),
        ));


    }
}
