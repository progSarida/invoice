<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShipmentTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('shipment_types')->delete();

        DB::table('shipment_types')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'name' => 'Atto giudiziario',
                'description' => NULL,
                'created_at' => '2025-08-18 10:31:46',
                'updated_at' => '2025-08-18 10:31:46',
            ),
            1 =>
            array (
                'id' => 2,
                'company_id' => 1,
                'name' => 'Bolgetta',
                'description' => NULL,
                'created_at' => '2025-08-18 10:31:46',
                'updated_at' => '2025-08-18 10:31:46',
            ),
            2 =>
            array (
                'id' => 3,
                'company_id' => 1,
                'name' => 'Mail',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            3 =>
            array (
                'id' => 4,
                'company_id' => 1,
                'name' => 'PEC',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            4 =>
            array (
                'id' => 5,
                'company_id' => 1,
                'name' => 'Posta ordinaria',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            5 =>
            array (
                'id' => 6,
                'company_id' => 1,
                'name' => 'Raccomandata',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            6 =>
            array (
                'id' => 7,
                'company_id' => 1,
                'name' => 'Raccomandata AR',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            7 =>
            array (
                'id' => 8,
                'company_id' => 1,
                'name' => 'SMA',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            8=>
            array (
                'id' => 9,
                'company_id' => 1,
                'name' => 'Ufficio postale',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
        ));


    }
}
