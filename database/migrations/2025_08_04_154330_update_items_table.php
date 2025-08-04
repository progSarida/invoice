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
        Schema::table('invoice_elements', function (Blueprint $table) {
            $table->string('transaction_type')->nullable();         // enum tipo transazione
            $table->string('code')->nullable();                     // codice prodotto
            $table->integer('quantity')->nullable();                // quantità
            $table->string('measure_unit')->nullable();             // unità di misura
            $table->decimal('unit_price')->nullable();              // prezzo unitario
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('transaction_type')->nullable();         // enum tipo transazione
            $table->date('start_date')->nullable();                 // data inizio periodo
            $table->date('end_date')->nullable();                   // data fine periodo
            $table->string('code')->nullable();                     // codice prodotto
            $table->integer('quantity')->nullable();                // quantità
            $table->string('measure_unit')->nullable();             // unità di misura
            $table->decimal('unit_price')->nullable();              // prezzo unitario
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
