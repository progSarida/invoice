<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('passive_items', function (Blueprint $blueprint) {
            // 1. Eliminiamo il vincolo della Foreign Key
            // Laravel di default usa: nomeTabella_nomeColonna_foreign
            $blueprint->dropForeign(['company_id']);

            // 2. Eliminiamo la colonna
            $blueprint->dropColumn('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passive_items', function (Blueprint $blueprint) {
            // In caso di rollback, riaggiungiamo la colonna e la relazione
            // $blueprint->foreignId('company_id')
            //     ->constrained('companies')
            //     ->onUpdate('cascade')
            //     ->onDelete('cascade'); // o 'restrict' a seconda della tua logica originale
        });
    }
};
