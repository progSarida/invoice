<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompaniesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('companies')->delete();

        DB::table('companies')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Sarida S.r.l.',
                'vat_number' => '01338160995',
                'address' => 'Via Mons. Vattuone, 9/6',
                'city_code' => 'I693',
                'is_active' => 1,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'STC S.r.l.',
                'vat_number' => '01704070992',
                'address' => 'Via Costaguta, 43',
                'city_code' => 'C621',
                'is_active' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));


    }
}
