<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LimitMotivationTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('limit_motivation_types')->delete();

        DB::table('limit_motivation_types')->insert(array (
            0 =>
            array (
                'id' => 1,
                'company_id' => 1,
                'name' => 'Dichiarazione di nullità degli accordi',
                'description' => NULL,
                'created_at' => '2025-06-27 11:33:54',
                'updated_at' => '2025-06-27 11:33:58',
            ),
            1 =>
            array (
                'id' => 2,
                'company_id' => 1,
                'name' => 'Dichiarazione di annullamento degli accordi',
                'description' => NULL,
                'created_at' => '2025-06-27 11:34:01',
                'updated_at' => '2025-06-27 11:34:04',
            ),
            2 =>
            array (
                'id' => 3,
                'company_id' => 1,
                'name' => 'Dichiarazione di revoca degli accordi',
                'description' => NULL,
                'created_at' => '2025-06-27 11:34:07',
                'updated_at' => '2025-06-27 11:34:07',
            ),
            3 =>
            array (
                'id' => 4,
                'company_id' => 1,
                'name' => 'Dichiarazione di risoluzione degli accordi',
                'description' => NULL,
                'created_at' => '2025-06-27 11:34:07',
                'updated_at' => '2025-06-27 11:34:07',
            ),
            4 =>
            array (
                'id' => 5,
                'company_id' => 1,
                'name' => 'Dichiarazione di risoluzione degli accordi',
                'description' => NULL,
                'created_at' => '2025-06-27 11:35:29',
                'updated_at' => '2025-06-27 11:35:29',
            ),
            5 =>
            array (
                'id' => 6,
                'company_id' => 1,
                'name' => 'Abbuoni previsti contrattualmente nonn dipendenti da un sopravvenuto accordo tra le parti',
                'description' => NULL,
                'created_at' => '2025-06-27 16:57:12',
                'updated_at' => '2025-06-27 16:57:12',
            ),
            6 =>
            array (
                'id' => 7,
                'company_id' => 1,
                'name' => 'Sconti previsti contrattualmente non dipendenti da un sopravvenuto accordo tra le parti',
                'description' => NULL,
                'created_at' => '2025-06-27 16:57:12',
                'updated_at' => '2025-06-27 16:57:12',
            ),
            7 =>
            array (
                'id' => 8,
                'company_id' => 1,
            'name' => 'Mancato pagamento del corrispetivo da parte del concessionario/committente per assoggettamento a procedure concorsuali (e assimilate) rimaste infruttuose',
                'description' => NULL,
                'created_at' => '2025-06-27 16:58:45',
                'updated_at' => '2025-06-27 16:58:45',
            ),
            8 =>
            array (
                'id' => 9,
                'company_id' => 1,
                'name' => 'Mancato pagamento del corrispetivo da parte del concessionario/committente per assoggettamento a procedure esecutive rimaste infruttuose',
                'description' => NULL,
                'created_at' => '2025-06-27 16:58:45',
                'updated_at' => '2025-06-27 16:58:45',
            ),
            9 =>
            array (
                'id' => 10,
                'company_id' => 1,
                'name' => 'Presenza di una clausola risolutiva parziale, contenuta nel contratto di compravendita',
                'description' => NULL,
                'created_at' => '2025-06-27 17:00:47',
                'updated_at' => '2025-06-27 17:00:47',
            ),
            10 =>
            array (
                'id' => 11,
                'company_id' => 1,
                'name' => 'Decreto-legge non convertito che aveva previsto un\'aliquota IVA più elevata',
                'description' => NULL,
                'created_at' => '2025-06-27 17:00:47',
                'updated_at' => '2025-06-27 17:00:47',
            ),
        ));


    }
}
