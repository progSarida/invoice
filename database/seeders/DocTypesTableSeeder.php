<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('doc_types')->delete();

        DB::table('doc_types')->insert(array (
            0 =>
            array (
                'id' => 1,
                'doc_group_id' => '1',
                'name' => 'TD00',
                'description' => "Preavviso di fattura",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'doc_group_id' => '2',
                'name' => 'TD01',
                'description' => "Fattura",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'doc_group_id' => '4',
                'name' => 'TD01',
                'description' => "Autofattura",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 =>
            array (
                'id' => 4,
                'doc_group_id' => '2',
                'name' => 'TD02',
                'description' => "Acconto/anticipo su fattura",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 =>
            array (
                'id' => 5,
                'doc_group_id' => '2',
                'name' => 'TD03',
                'description' => "Acconto/anticipo su parcella",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 =>
            array (
                'id' => 6,
                'doc_group_id' => '3',
                'name' => 'TD04',
                'description' => "Nota di credito",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 =>
            array (
                'id' => 7,
                'doc_group_id' => '3',
                'name' => 'TD05',
                'description' => "Nota di debito",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 =>
            array (
                'id' => 8,
                'doc_group_id' => '2',
                'name' => 'TD06',
                'description' => "Parcella",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 =>
            array (
                'id' => 9,
                'doc_group_id' => '4',
                'name' => 'TD16',
                'description' => "Integrazione fattura reverse charge interno",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 =>
            array (
                'id' => 10,
                'doc_group_id' => '4',
                'name' => 'TD17',
                'description' => "Integrazione/autofattura per acquisto servizi dall'estero",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 =>
            array (
                'id' => 11,
                'doc_group_id' => '4',
                'name' => 'TD18',
                'description' => "Integrazione/autofattura per acquisto di beni intracomunitari",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 =>
            array (
                'id' => 12,
                'doc_group_id' => '4',
                'name' => 'TD19',
                'description' => "Integrazione/autofattura per acquisto di beni ex art. 17 c. 2 DPR 633/72",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 =>
            array (
                'id' => 13,
                'doc_group_id' => '4',
                'name' => 'TD20',
                'description' => "Autofattura per regolarizzazione e integrazione delle fatture in reverse charge",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            13 =>
            array (
                'id' => 14,
                'doc_group_id' => '4',
                'name' => 'TD21',
                'description' => "Autofattura per splafonamento",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            14 =>
            array (
                'id' => 15,
                'doc_group_id' => '4',
                'name' => 'TD22',
                'description' => "Estrazione beni da Deposito IVA",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            15 =>
            array (
                'id' => 16,
                'doc_group_id' => '4',
                'name' => 'TD23',
                'description' => "Estrazione beni da Deposito IVA con versamento dell'IVA",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            16 =>
            array (
                'id' => 17,
                'doc_group_id' => '2',
                'name' => 'TD24',
                'description' => "Fattura differita di cui all'art. 21, comma 4, lett. a",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            17 =>
            array (
                'id' => 18,
                'doc_group_id' => '2',
                'name' => 'TD25',
                'description' => "Fattura differita di cui all'art. 21, comma 4, terzo periodo lett. b",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            18 =>
            array (
                'id' => 19,
                'doc_group_id' => '2',
                'name' => 'TD26',
                'description' => "Cessione beni ammortizzabili",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            19 =>
            array (
                'id' => 20,
                'doc_group_id' => '4',
                'name' => 'TD26',
                'description' => "Autofattura per passaggi interni (ex art. 36 DPR 633/72)",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            20 =>
            array (
                'id' => 21,
                'doc_group_id' => '4',
                'name' => 'TD27',
                'description' => "Fattura per autoconsumo o per cessioni gratuite senza rivalsa",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            21 =>
            array (
                'id' => 22,
                'doc_group_id' => '4',
                'name' => 'TD28',
                'description' => "Acquisti da San Marino con IVA (fattura cartacea)",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            22 =>
            array (
                'id' => 23,
                'doc_group_id' => '4',
                'name' => 'TD29',
                'description' => "Comunicazione per omessa o irregolare fatturazione",
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
    }
}
