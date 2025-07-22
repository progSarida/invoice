<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DocTypeSectionalTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('doc_type_sectional')->delete();
        
        \DB::table('doc_type_sectional')->insert(array (
            0 => 
            array (
                'id' => 1,
                'sectional_id' => 1,
                'doc_type_id' => 1,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'sectional_id' => 1,
                'doc_type_id' => 2,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'sectional_id' => 1,
                'doc_type_id' => 3,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'sectional_id' => 1,
                'doc_type_id' => 6,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'sectional_id' => 2,
                'doc_type_id' => 1,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'sectional_id' => 2,
                'doc_type_id' => 2,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'sectional_id' => 2,
                'doc_type_id' => 3,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 => 
            array (
                'id' => 8,
                'sectional_id' => 2,
                'doc_type_id' => 6,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}