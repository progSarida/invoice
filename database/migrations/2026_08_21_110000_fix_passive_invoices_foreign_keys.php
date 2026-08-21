<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sistema i vincoli delle fatture passive:
     *
     * 1. passive_invoices.parent_id (note di credito/debito) era una semplice colonna
     *    senza foreign key: viene creato il vincolo, senza ON DELETE, così una fattura
     *    con documenti collegati non può essere eliminata.
     * 2. passive_items.passive_invoice_id passa a ON DELETE CASCADE: le voci sono parte
     *    del documento e devono sparire con esso.
     *
     * Gli altri riferimenti a passive_invoices (passive_payments, postal_expenses)
     * restano invariati, in RESTRICT.
     */
    public function up(): void
    {
        // 1. Foreign key su passive_invoices.parent_id ------------------------------------
        if ($this->getForeignKeyConstraintName('passive_invoices', 'parent_id')) {
            echo "⚠️  Foreign key su passive_invoices.parent_id già presente, skip...\n";
        } else {
            $orphans = $this->getOrphanParentIds();

            if ($orphans->isNotEmpty()) {
                // Riferimenti a fatture non più esistenti: impedirebbero la creazione del
                // vincolo e sono comunque già rotti, quindi vengono azzerati.
                DB::table('passive_invoices')->whereIn('id', $orphans)->update(['parent_id' => null]);

                echo "⚠️  Azzerato parent_id orfano su {$orphans->count()} fatture passive (id: "
                    . $orphans->implode(', ') . ")\n";
            }

            Schema::table('passive_invoices', function (Blueprint $blueprint) {
                $blueprint->foreign('parent_id')
                    ->references('id')
                    ->on('passive_invoices')
                    ->onUpdate('cascade');
            });

            echo "✅  Creata foreign key su passive_invoices.parent_id\n";
        }

        // 2. ON DELETE CASCADE su passive_items.passive_invoice_id -------------------------
        $constraintName = $this->getForeignKeyConstraintName('passive_items', 'passive_invoice_id');

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per passive_items.passive_invoice_id non trovato, skip...\n";
            return;
        }

        Schema::table('passive_items', function (Blueprint $blueprint) use ($constraintName) {
            $blueprint->dropForeign($constraintName);

            $blueprint->foreign('passive_invoice_id')
                ->references('id')
                ->on('passive_invoices')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        echo "✅  Impostato CASCADE su passive_items.passive_invoice_id\n";
    }

    public function down(): void
    {
        // 1. Rimozione della foreign key su passive_invoices.parent_id ---------------------
        $parentConstraint = $this->getForeignKeyConstraintName('passive_invoices', 'parent_id');

        if ($parentConstraint) {
            Schema::table('passive_invoices', function (Blueprint $blueprint) use ($parentConstraint) {
                $blueprint->dropForeign($parentConstraint);
            });

            echo "✅  Rimossa foreign key da passive_invoices.parent_id\n";
        } else {
            echo "⚠️  Foreign key su passive_invoices.parent_id non trovata durante il rollback, skip...\n";
        }

        // 2. Ripristino di passive_items.passive_invoice_id senza CASCADE ------------------
        $constraintName = $this->getForeignKeyConstraintName('passive_items', 'passive_invoice_id');

        if (!$constraintName) {
            echo "⚠️  Foreign key constraint per passive_items.passive_invoice_id non trovato durante il rollback, skip...\n";
            return;
        }

        Schema::table('passive_items', function (Blueprint $blueprint) use ($constraintName) {
            $blueprint->dropForeign($constraintName);

            $blueprint->foreign('passive_invoice_id')
                ->references('id')
                ->on('passive_invoices')
                ->onUpdate('cascade');
        });

        echo "✅  Rimosso CASCADE da passive_items.passive_invoice_id\n";
    }

    /**
     * Id delle fatture passive il cui parent_id punta ad una fattura inesistente
     */
    private function getOrphanParentIds(): \Illuminate\Support\Collection
    {
        return DB::table('passive_invoices')
            ->whereNotNull('parent_id')
            ->whereNotIn('parent_id', DB::table('passive_invoices')->select('id'))
            ->pluck('id');
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
