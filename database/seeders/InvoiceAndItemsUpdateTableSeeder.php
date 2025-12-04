<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceAndItemsUpdateTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Aggiornamento aliquote voci fatture importate da vecchio programma
        DB::statement("
            UPDATE `invoice_items`
            SET `vat_code_type` = CASE
                WHEN description = 'Imposta di Bollo' THEN 'vc06a'
                WHEN description = 'Rimborsi' THEN 'vc06'
                ELSE 'vc01'
            END
            WHERE description IN (
                'Pubblicità',
                'Pubblicità Ordinaria',
                'Pubblicità Temporanea',
                'O.S.A.P.',
                'O.S.A.P. Ordinaria',
                'O.S.A.P. Temporanea',
                'Affissioni',
                'Diritto Pubbliche Affissioni',
                'Imposta di Bollo',
                'Importo',
                'Rimborsi',
                'Spese'
            )
            AND invoice_id <= 6338;
        ");

        // ---

        // Aggiornamento totali voci fatture importate da vecchio programma
        DB::statement("
            UPDATE `invoice_items`
            SET `total` = `amount`
            WHERE invoice_id <= 6338;
        ");

        // ---

        // Aggiornamento conto bancario fatture importate da vecchio programma (dove necessario)
        DB::statement("
            UPDATE `invoices`
            SET `bank_account_id` = 4
            WHERE id <= 6338
            AND `bank_account_id` IS NULL;
        ");
    }
}
