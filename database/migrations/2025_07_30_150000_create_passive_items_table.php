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
        Schema::create('passive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');                // id tenant
            $table->foreignId('passive_invoice_id')->constrained()->onUpdate('cascade');        // id fornitore
            $table->string('description')->nullable();                                          // descrizione
            $table->integer('quantity')->nullable();                                            // quantità
            $table->decimal('unit_price',10,2)->nullable();                                     // prezzo unitario
            $table->decimal('total_price',10,2)->nullable();                                    // prezzo totale
            $table->decimal('vat_rate',10,2)->nullable();                                       // aliquota IVA
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('passive_items');
        Schema::enableForeignKeyConstraints();
    }
};
