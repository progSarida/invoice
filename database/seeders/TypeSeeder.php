<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('accrual_types')->delete();

        DB::table('accrual_types')->insert(array (
            0 =>
            array (
                'id' => 1,
                'order' => 1,
                'name' => 'Ordinaria',
                'ref' => 'ordinary',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),
            1 =>
            array (
                'id' => 2,
                'order' => 2,
                'name' => 'Coattiva',
                'ref' => 'coercive',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),
            2 =>
            array (
                'id' => 3,
                'order' => 3,
                'name' => 'Accertamento',
                'ref' => 'verification',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),

            3 =>
            array (
                'id' => 4,
                'order' => 4,
                'name' => 'Servizi',
                'ref' => 'service',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),
        ));

        DB::table('manage_types')->delete();

        DB::table('manage_types')->insert(array (
            0 =>
            array (
                'id' => 1,
                'order' => 1,
                'name' => 'Locazione apparecchiatura fissa',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),
            1 =>
            array (
                'id' => 2,
                'order' => 2,
                'name' => 'Locazione apparecchiatura mobile',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),
            2 =>
            array (
                'id' => 3,
                'order' => 3,
                'name' => 'Gestione violazioni CDS',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),

            3 =>
            array (
                'id' => 4,
                'order' => 4,
                'name' => 'Gestione e riscossione violazioni CDS',
                'description' => '',
                'created_at' => '2025-05-30 11:30:00',
                'updated_at' => '2025-05-30 11:30:00',
            ),
        ));
    }
}
