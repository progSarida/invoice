<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceElementsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('invoice_elements')->delete();

        DB::table('invoice_elements')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Rimborso spese notifica',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 =>
            array (
                'id' => 4,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 =>
            array (
                'id' => 5,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 =>
            array (
                'id' => 6,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 =>
            array (
                'id' => 7,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 =>
            array (
                'id' => 8,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 =>
            array (
                'id' => 9,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 =>
            array (
                'id' => 10,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 =>
            array (
                'id' => 11,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 =>
            array (
                'id' => 12,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 =>
            array (
                'id' => 13,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            13 =>
            array (
                'id' => 14,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            14 =>
            array (
                'id' => 15,
                'name' => '',
                'description' => "",
                'amount' => 0,
                'vat_code_type' => '',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
    }
}
