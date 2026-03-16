<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Array di configurazione per gestire la rimozione dei CASCADE.
     * Imposta 'remove_cascade' a true per rimuovere il CASCADE, false per mantenerlo.
     *
     * TOTALE: 49 FOREIGN KEYS CON CASCADE
     */
    private array $foreignKeys = [
        // BANK_ACCOUNTS TABLE
        [
            'table' => 'bank_accounts',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true, // Cambia a true per rimuovere CASCADE
        ],

        // INVOICES TABLE (CREATE)
        [
            'table' => 'invoices',
            'column' => 'parent_id',
            'references' => 'id',
            'on' => 'invoices',
            'remove_cascade' => true,
        ],
        [
            'table' => 'sdi_notifications',
            'column' => 'invoice_id',
            'references' => 'id',
            'on' => 'invoices',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoice_items',
            'column' => 'invoice_id',
            'references' => 'id',
            'on' => 'invoices',
            'remove_cascade' => true,
        ],

        // COMPANY RELATED TABLES
        [
            'table' => 'curators',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'productors',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'sectionals',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'fiscal_profiles',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'social_contributions',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'withholdings',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'stamp_duties',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],

        // DOC_GROUPS/DOC_TYPES/COMPANY_DOCS/DOC_TYPE_SECTIONAL
        [
            'table' => 'doc_types',
            'column' => 'doc_group_id',
            'references' => 'id',
            'on' => 'doc_groups',
            'remove_cascade' => true,
        ],
        [
            'table' => 'company_docs',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'company_docs',
            'column' => 'doc_type_id',
            'references' => 'id',
            'on' => 'doc_types',
            'remove_cascade' => true,
        ],
        [
            'table' => 'doc_type_sectional',
            'column' => 'sectional_id',
            'references' => 'id',
            'on' => 'sectionals',
            'remove_cascade' => true,
        ],
        [
            'table' => 'doc_type_sectional',
            'column' => 'doc_type_id',
            'references' => 'id',
            'on' => 'doc_types',
            'remove_cascade' => true,
        ],

        // NEW_CONTRACTS/CONTRACT_DETAILS
        [
            'table' => 'new_contracts',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'new_contracts',
            'column' => 'client_id',
            'references' => 'id',
            'on' => 'clients',
            'remove_cascade' => true,
        ],
        [
            'table' => 'new_contracts',
            'column' => 'accrual_type_id',
            'references' => 'id',
            'on' => 'accrual_types',
            'remove_cascade' => true,
        ],
        [
            'table' => 'contract_details',
            'column' => 'contract_id',
            'references' => 'id',
            'on' => 'new_contracts',
            'remove_cascade' => true,
        ],

        // ACTIVE_PAYMENTS/PASSIVE_PAYMENTS
        [
            'table' => 'active_payments',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'active_payments',
            'column' => 'invoice_id',
            'references' => 'id',
            'on' => 'invoices',
            'remove_cascade' => true,
        ],
        [
            'table' => 'passive_payments',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],
        [
            'table' => 'passive_payments',
            'column' => 'passive_invoice_id',
            'references' => 'id',
            'on' => 'passive_invoices',
            'remove_cascade' => true,
        ],

        // COMPANIES TABLE (ALTER - provinces)
        [
            'table' => 'companies',
            'column' => 'register_province_id',
            'references' => 'id',
            'on' => 'provinces',
            'remove_cascade' => true,
        ],
        [
            'table' => 'companies',
            'column' => 'rea_province_id',
            'references' => 'id',
            'on' => 'provinces',
            'remove_cascade' => true,
        ],
        [
            'table' => 'companies',
            'column' => 'state_id',
            'references' => 'id',
            'on' => 'states',
            'remove_cascade' => true,
        ],

		// CLIENTS
        [
            'table' => 'clients',
            'column' => 'state_id',
            'references' => 'id',
            'on' => 'states',
            'remove_cascade' => true,
        ],

        // INVOICES TABLE (ALTER - various foreign keys)
        [
            'table' => 'invoices',
            'column' => 'accrual_type_id',
            'references' => 'id',
            'on' => 'accrual_types',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoices',
            'column' => 'manage_type_id',
            'references' => 'id',
            'on' => 'manage_types',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoices',
            'column' => 'contract_id',
            'references' => 'id',
            'on' => 'new_contracts',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoices',
            'column' => 'doc_type_id',
            'references' => 'id',
            'on' => 'doc_types',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoices',
            'column' => 'sectional_id',
            'references' => 'id',
            'on' => 'sectionals',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoices',
            'column' => 'limit_motivation_type_id',
            'references' => 'id',
            'on' => 'limit_motivation_types',
            'remove_cascade' => true,
        ],
        [
            'table' => 'invoices',
            'column' => 'contract_detail_id',
            'references' => 'id',
            'on' => 'contract_details',
            'remove_cascade' => true,
        ],

        // LIMIT_MOTIVATION_TYPES
        [
            'table' => 'limit_motivation_types',
            'column' => 'company_id',
            'references' => 'id',
            'on' => 'companies',
            'remove_cascade' => true,
        ],

        // INVOICE_ITEMS (ALTER - invoice_element_id)
        [
            'table' => 'invoice_items',
            'column' => 'invoice_element_id',
            'references' => 'id',
            'on' => 'invoice_elements',
            'remove_cascade' => true,
        ],

        // 39. COMPANY_USER
        [
            'table' => 'company_user',
            'column' => 'user_id',
            'references' => 'id',
            'on' => 'users',
            'remove_cascade' => true,
        ],

        // INSURANCES/AGENCIES/BAILS
        [
            'table' => 'agencies',
            'column' => 'insurance_id',
            'references' => 'id',
            'on' => 'insurances',
            'remove_cascade' => true,
        ],
        [
            'table' => 'bails',
            'column' => 'client_id',
            'references' => 'id',
            'on' => 'clients',
            'remove_cascade' => true,
        ],
        [
            'table' => 'bails',
            'column' => 'contract_id',
            'references' => 'id',
            'on' => 'new_contracts',
            'remove_cascade' => true,
        ],

        // ATTACHMENTS
        [
            'table' => 'attachments',
            'column' => 'client_id',
            'references' => 'id',
            'on' => 'clients',
            'remove_cascade' => true,
        ],
        [
            'table' => 'attachments',
            'column' => 'contract_id',
            'references' => 'id',
            'on' => 'new_contracts',
            'remove_cascade' => true,
        ],

        // SDI_REQUESTS
        [
            'table' => 'sdi_requests',
            'column' => 'invoice_id',
            'references' => 'id',
            'on' => 'invoices',
            'remove_cascade' => true,
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->foreignKeys as $fk) {
            if ($fk['remove_cascade']) {
                $this->removeCascadeFromForeignKey(
                    $fk['table'],
                    $fk['column'],
                    $fk['references'],
                    $fk['on']
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->foreignKeys as $fk) {
            if ($fk['remove_cascade']) {
                $this->addCascadeToForeignKey(
                    $fk['table'],
                    $fk['column'],
                    $fk['references'],
                    $fk['on']
                );
            }
        }
    }

    /**
     * Rimuove il CASCADE da una foreign key esistente
     */
    private function removeCascadeFromForeignKey(string $table, string $column, string $references, string $on): void
    {
        // Verifica se il constraint esiste
        $constraintName = $this->getForeignKeyConstraintName($table, $column);

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per {$table}.{$column} non trovato, skip...\n";
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $references, $on, $constraintName) {
            // Rimuove il constraint esistente usando il nome effettivo
            $blueprint->dropForeign($constraintName);

            // Ricrea il constraint SENZA onDelete('cascade')
            $blueprint->foreign($column)
                ->references($references)
                ->on($on)
                ->onUpdate('cascade'); // Manteniamo l'onUpdate cascade
        });

        echo "✅  Rimosso CASCADE da {$table}.{$column}\n";
    }

    /**
     * Aggiunge nuovamente il CASCADE ad una foreign key (per il rollback)
     */
    private function addCascadeToForeignKey(string $table, string $column, string $references, string $on): void
    {
        // Verifica se il constraint esiste
        $constraintName = $this->getForeignKeyConstraintName($table, $column);

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per {$table}.{$column} non trovato durante il rollback, skip...\n";
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $references, $on, $constraintName) {
            // Rimuove il constraint senza cascade
            $blueprint->dropForeign($constraintName);

            // Ricrea il constraint CON onDelete('cascade')
            $blueprint->foreign($column)
                ->references($references)
                ->on($on)
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        echo "✅  Ripristinato CASCADE su {$table}.{$column}\n";
    }

    /**
     * Ottiene il nome effettivo del constraint della foreign key
     */
    private function getForeignKeyConstraintName(string $table, string $column): ?string
    {
        $databaseName = config('database.connections.mysql.database');

        $constraint = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ", [$databaseName, $table, $column]);

        return $constraint ? $constraint->CONSTRAINT_NAME : null;
    }
};
