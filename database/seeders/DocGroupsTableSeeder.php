<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocGroupsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('doc_groups')->delete();

        DB::table('doc_groups')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Preavvisi di fattura',
                'description' => NULL,
                'created_at' => '2025-06-05 13:25:56',
                'updated_at' => '2025-06-05 13:27:41',
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'Fatture',
                'description' => NULL,
                'created_at' => '2025-06-05 13:26:04',
                'updated_at' => '2025-06-05 13:26:04',
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Note di variazione',
                'description' => NULL,
                'created_at' => '2025-06-05 13:27:30',
                'updated_at' => '2025-06-05 13:27:30',
            ),
            3 =>
            array (
                'id' => 4,
                'name' => 'Autofatture',
                'description' => NULL,
                'created_at' => '2025-06-05 13:27:55',
                'updated_at' => '2025-06-05 13:27:55',
            ),
        ));


    }
}
