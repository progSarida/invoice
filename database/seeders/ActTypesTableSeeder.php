<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('act_types')->delete();

        DB::table('act_types')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'name' => 'Avviso di accertamento',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            1 =>
            array (
                'id' => 2,
                'company_id' => 1,
                'name' => 'Verbale di accertamento',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            2 =>
            array (
                'id' => 3,
                'company_id' => 1,
                'name' => 'Ingiunzione di pagamento',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            3 =>
            array (
                'id' => 4,
                'company_id' => 1,
                'name' => 'Avviso di messa in mora',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            4 =>
            array (
                'id' => 5,
                'company_id' => 1,
                'name' => 'Avviso di intimazione ad adempiere',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            5 =>
            array (
                'id' => 6,
                'company_id' => 1,
                'name' => 'Pignoramento',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            6 =>
            array (
                'id' => 7,
                'company_id' => 1,
                'name' => 'Procedura cautelare',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
            7 =>
            array (
                'id' => 8,
                'company_id' => 1,
                'name' => 'Procedura di coazione',
                'description' => NULL,
                'created_at' => '2025-08-18 10:30:43',
                'updated_at' => '2025-08-18 10:30:43',
            ),
        ));


    }
}
