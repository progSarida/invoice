<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionalsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('sectionals')->delete();

        DB::table('sectionals')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'description' => '00',
                'client_type' => 'private',
                'doc_type' => '',
                'numeration_type' => 'annual',
                'progressive' => '1',
                'created_at' => '2025-07-03 09:36:05',
                'updated_at' => '2025-07-03 09:36:05',
            ),
            1 =>
            array (
                'id' => 2,
                'company_id' => 1,
                'description' => '02',
                'client_type' => 'public',
                'doc_type' => '',
                'numeration_type' => 'annual',
                'progressive' => '1',
                'created_at' => '2025-07-03 09:36:21',
                'updated_at' => '2025-07-03 09:36:21',
            ),
        ));


    }
}
