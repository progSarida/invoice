<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CompanyDocsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('company_docs')->delete();
        
        \DB::table('company_docs')->insert(array (
            0 => 
            array (
                'id' => 1,
                'company_id' => 1,
                'doc_type_id' => 1,
                'created_at' => '2025-07-03 09:16:34',
                'updated_at' => '2025-07-03 09:16:34',
            ),
            1 => 
            array (
                'id' => 2,
                'company_id' => 1,
                'doc_type_id' => 2,
                'created_at' => '2025-07-03 09:16:34',
                'updated_at' => '2025-07-03 09:16:34',
            ),
            2 => 
            array (
                'id' => 3,
                'company_id' => 1,
                'doc_type_id' => 3,
                'created_at' => '2025-07-03 09:16:34',
                'updated_at' => '2025-07-03 09:16:34',
            ),
            3 => 
            array (
                'id' => 4,
                'company_id' => 1,
                'doc_type_id' => 6,
                'created_at' => '2025-07-03 09:16:34',
                'updated_at' => '2025-07-03 09:16:34',
            ),
        ));
        
        
    }
}