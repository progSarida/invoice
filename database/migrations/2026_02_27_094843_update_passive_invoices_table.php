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
        Schema::table('passive_invoices', function (Blueprint $table) {
            $table->decimal('total_doc',10,2)->nullable();                                                          // totale documento fattura
            $table->decimal('total_note',10,2)->nullable()->after('total_payment');                                 // totale note di credito
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
