<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StampDutiesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('stamp_duties')->delete();
        
        \DB::table('stamp_duties')->insert(array (
            0 => 
            array (
                'id' => 1,
                'company_id' => 1,
                'active' => 1,
                'value' => '77.47',
                'virtual_stamp' => 1,
                'virtual_amount' => '2.00',
                'add_row' => 1,
                'row_description' => 'Imposta di Bollo escl. Art. 15 ex DPR 633/72',
                'amount' => '2.00',
                'created_at' => '2025-07-01 09:16:13',
                'updated_at' => '2025-07-03 09:36:21',
            ),
        ));
        
        
    }
}