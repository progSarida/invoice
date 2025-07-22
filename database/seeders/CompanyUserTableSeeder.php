<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyUserTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('company_user')->delete();

        DB::table('company_user')->insert(array (
            0 =>
            array (
                'company_id' => 1,
                'user_id' => 1,
            ),
            1 =>
            array (
                'company_id' => 2,
                'user_id' => 1,
            ),
            2 =>
            array (
                'company_id' => 1,
                'user_id' => 2,
            ),
            3 =>
            array (
                'company_id' => 2,
                'user_id' => 2,
            ),
            4 =>
            array (
                'company_id' => 1,
                'user_id' => 3,
            ),
            5 =>
            array (
                'company_id' => 2,
                'user_id' => 3,
            ),
            6 =>
            array (
                'company_id' => 1,
                'user_id' => 4,
            ),
            7 =>
            array (
                'company_id' => 2,
                'user_id' => 4,
            ),
        ));


    }
}
