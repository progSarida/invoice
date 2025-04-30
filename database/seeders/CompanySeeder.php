<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $query = "INSERT INTO `companies` (`id`, `name`, `vat_number`, `address`, `city_code`, `is_active`) VALUES
                    (1, 'Sarida S.r.l.', '01338160995', 'Via Mons. Vattuone, 9/6', 'I693', 1),
                    (2, 'STC S.r.l.', '01704070992', 'Via Costaguta, 43', 'C621', 0);";
        DB::insert($query);

        $query = "INSERT INTO `bank_accounts` (`id`, `iban`, `company_id`,  `name`, `bic`) VALUES
                    (1, 'IT15T0306932230100000064212', 1, 'Intesa San Paolo', 'BREUITM1'),
                    (2, 'IT71I0503432230000000105796', 1, 'Banco Popolare', 'BAPPIT21R90'),
                    (3, 'IT18S0503432230000000375578', 2, 'Banca Popolare Societa\' Cooperativa', '');";
        DB::insert($query);
    }
}
