<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CompaniesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('companies')->delete();
        
        \DB::table('companies')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Sarida S.r.l.',
                'vat_number' => '01338160995',
                'tax_number' => '01338160995',
                'state_id' => 111,
                'address' => 'Via Mons. Vattuone, 9/6',
                'city_code' => 'I693',
                'place' => NULL,
                'phone' => NULL,
                'email' => NULL,
                'pec' => NULL,
                'fax' => NULL,
                'register' => NULL,
                'register_province_id' => NULL,
                'register_number' => NULL,
                'register_date' => NULL,
                'rea_province_id' => NULL,
                'rea_number' => NULL,
                'nominal_capital' => NULL,
                'shareholders' => NULL,
                'liquidation' => NULL,
                'is_active' => 1,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-07-23 07:10:32',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'STC S.r.l.',
                'vat_number' => '01704070992',
                'tax_number' => '01704070992',
                'state_id' => NULL,
                'address' => 'Via Costaguta, 43',
                'city_code' => 'C621',
                'place' => NULL,
                'phone' => NULL,
                'email' => NULL,
                'pec' => NULL,
                'fax' => NULL,
                'register' => NULL,
                'register_province_id' => NULL,
                'register_number' => NULL,
                'register_date' => NULL,
                'rea_province_id' => NULL,
                'rea_number' => NULL,
                'nominal_capital' => NULL,
                'shareholders' => NULL,
                'liquidation' => NULL,
                'is_active' => 0,
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));
        
        
    }
}