<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankAccountsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('bank_accounts')->delete();

        DB::table('bank_accounts')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'name' => 'Intesa San Paolo',
                'iban' => 'IT15T0306932230100000064212',
                'bic' => 'BREUITM1',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'company_id' => 1,
                'name' => 'Banco Popolare',
                'iban' => 'IT71I0503432230000000105796',
                'bic' => 'BAPPIT21R90',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'company_id' => 2,
                'name' => 'Banca Popolare Societa\' Cooperativa',
                'iban' => 'IT18S0503432230000000375578',
                'bic' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));


    }
}
