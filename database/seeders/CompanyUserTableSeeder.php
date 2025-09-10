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
                'is_manager' => 1,
                'created_at' => '2025-04-27 08:37:11',
                'updated_at' => '2025-04-27 08:37:11',
            ),
            1 =>
            array (
                'company_id' => 2,
                'user_id' => 1,
                'is_manager' => 0,
                'created_at' => '2025-04-27 08:37:11',
                'updated_at' => '2025-04-27 08:37:11',
            ),
            2 =>
            array (
                'company_id' => 1,
                'user_id' => 2,
                'is_manager' => 1,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-07-03 09:50:51',
            ),
            3 =>
            array (
                'company_id' => 2,
                'user_id' => 2,
                'is_manager' => 1,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-07-03 09:50:51',
            ),
            4 =>
            array (
                'company_id' => 1,
                'user_id' => 3,
                'is_manager' => 1,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-04-30 12:58:35',
            ),
            5 =>
            array (
                'company_id' => 2,
                'user_id' => 3,
                'is_manager' => 0,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-04-30 12:58:35',
            ),
            6 =>
            array (
                'company_id' => 1,
                'user_id' => 4,
                'is_manager' => 0,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-07-03 09:50:44',
            ),
            7 =>
            array (
                'company_id' => 2,
                'user_id' => 4,
                'is_manager' => 0,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-07-03 09:50:44',
            ),
        ));


    }
}
