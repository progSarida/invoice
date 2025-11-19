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
            $table->string('transaction_type')->nullable()->after('description');       // enum tipo transazione
            $table->string('code')->nullable()->after('transaction_type');              // codice prodotto
            $table->integer('quantity')->nullable()->after('code');                     // quantità
            $table->string('measure_unit')->nullable()->after('quantity');              // unità di misura
            $table->decimal('unit_price')->nullable()->after('measure_unit');           // prezzo unitario
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('transaction_type')->nullable()->after('description');       // enum tipo transazione
            $table->date('start_date')->nullable()->after('transaction_type');          // data inizio periodo
            $table->date('end_date')->nullable()->after('start_date');                  // data fine periodo
            $table->string('code')->nullable()->after('end_date');                      // codice prodotto
            $table->integer('quantity')->nullable()->after('code');                     // quantità
            $table->string('measure_unit')->nullable()->after('quantity');              // unità di misura
            $table->decimal('unit_price')->nullable()->after('measure_unit');           // prezzo unitario
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
