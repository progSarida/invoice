<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceAndItemsUpdateTableSeeder extends Seeder
{
    /**
     * Archivio query usate per l'aggiornamento dei dati delle fatture importate dal vecchio programma
     */
    public function run(): void
    {
        // 1.

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

        // 2.

        // Aggiornamento totali voci fatture importate da vecchio programma
        DB::statement("
            UPDATE `invoice_items`
            SET `total` = `amount`
            WHERE invoice_id <= 6338;
        ");

        // 3.

        // Aggiornamento conto bancario fatture importate da vecchio programma (dove necessario)
        DB::statement("
            UPDATE `invoices`
            SET `bank_account_id` = 4
            WHERE id <= 6338
            AND `bank_account_id` IS NULL;
        ");

        // 4.

        // Aggiornamento sezionario vecchie fatture a privati
        DB::statement("
            UPDATE `invoices`
            SET `sectional_id` = 1
            WHERE `contract_id` IS NULL AND `section` = 0;
        ");

        // 5.

        // Aggiornamento tipo documento vecchie fatture a privati
        DB::statement("
            UPDATE `invoices`
            SET `doc_type_id` = CASE
                WHEN `invoice_type` = 'invoice_notice' THEN 1
                WHEN `invoice_type` = 'invoice' THEN 2
                WHEN `invoice_type` = 'credit_note' THEN 5
                ELSE `doc_type_id`
            END
            WHERE `contract_id` IS NULL;
        ");

        // 6.

        // Unificazione fatture con cliente con doppione
        DB::statement("
            UPDATE `invoices`
            SET `client_id` = CASE
                WHEN `client_id` = 296 THEN 295
                WHEN `client_id` = 297 THEN 239
                WHEN `client_id` = 298 THEN 239
                WHEN `client_id` = 205 THEN 199
                ELSE `client_id`
            END
            WHERE `contract_id` IS NULL;
        ");

        // 7.

        // Cancellazione doppioni di clienti
        DB::statement("
            DELETE FROM `clients` WHERE id IN (205, 297, 298, 296);
        ");
    }
}
