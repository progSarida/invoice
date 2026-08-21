<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ripristina ON DELETE CASCADE sulla sola foreign key invoice_items.invoice_id
     * (rimossa da 2026_03_16_085536_remove_on_delete_cascade).
     *
     * Le voci sono parte integrante della fattura: eliminando il documento devono
     * sparire con esso. Tutte le altre foreign key verso invoices restano senza
     * cascade: quelle relazioni bloccano l'eliminazione (vedi Invoice::deleting).
     */
    private string $table = 'invoice_items';

    private string $column = 'invoice_id';

    private string $references = 'id';

    private string $on = 'invoices';

    public function up(): void
    {
        $constraintName = $this->getForeignKeyConstraintName($this->table, $this->column);

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per {$this->table}.{$this->column} non trovato, skip...\n";
            return;
        }

        Schema::table($this->table, function (Blueprint $blueprint) use ($constraintName) {
            $blueprint->dropForeign($constraintName);

            // Ricrea il constraint CON onDelete('cascade')
            $blueprint->foreign($this->column)
                ->references($this->references)
                ->on($this->on)
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        echo "✅  Ripristinato CASCADE su {$this->table}.{$this->column}\n";
    }

    public function down(): void
    {
        $constraintName = $this->getForeignKeyConstraintName($this->table, $this->column);

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per {$this->table}.{$this->column} non trovato durante il rollback, skip...\n";
            return;
        }

        Schema::table($this->table, function (Blueprint $blueprint) use ($constraintName) {
            $blueprint->dropForeign($constraintName);

            // Ricrea il constraint SENZA onDelete('cascade')
            $blueprint->foreign($this->column)
                ->references($this->references)
                ->on($this->on)
                ->onUpdate('cascade');
        });

        echo "✅  Rimosso CASCADE da {$this->table}.{$this->column}\n";
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
