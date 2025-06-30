<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('users')->delete();

        DB::table('users')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'superadmin',
                'email' => 'mirkopas85@gmail.com',
                'email_verified_at' => NULL,
                'password' => '$2y$12$QMtr51u0VsSyH3ha9tBt1u6D0PTR890.bYlg2ph7l3CuHKgzHzbLO',
                'is_admin' => 1,
                'remember_token' => NULL,
                'created_at' => '2025-04-27 08:37:11',
                'updated_at' => '2025-04-27 08:37:11',
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'michele',
                'email' => 'michele.gavazzi@sarida.it',
                'email_verified_at' => NULL,
                'password' => '$2y$12$gKo.OiTkDyUecSDnCJv1KexslrROThR53hOjaHz1wCGjGWTaW5bUC',
                'is_admin' => 1,
                'remember_token' => NULL,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-04-30 12:58:35',
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'riccardo',
                'email' => 'riccardo.sambuceti@sarida.it',
                'email_verified_at' => NULL,
                'password' => '$2y$12$vGmI72L2XXg0JYPMEB9bDuF0Ce03KQ8qRgpHPkhBqQisbysbaIwi2',
                'is_admin' => 1,
                'remember_token' => NULL,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-04-30 12:58:35',
            ),
            3 =>
            array (
                'id' => 4,
                'name' => 'daniele',
                'email' => 'contabilita@sarida.it',
                'email_verified_at' => NULL,
                'password' => '$2y$12$wiyG4yrluNCqI80yurW/Yej1IYmOfjFNy6qowx5e61wsdGDGY7YO6',
                'is_admin' => 0,
                'remember_token' => NULL,
                'created_at' => '2025-04-30 12:58:35',
                'updated_at' => '2025-06-30 07:18:16',
            ),
        ));


    }
}
