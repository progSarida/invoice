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
        Schema::create('suppliers', function (Blueprint $table) {                               // tabella fornitori
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');                // id tenant
            $table->string('denomination');                                                     // nome fornitore
            $table->string('tax_code')->nullable();                                             // codice fiscale
            $table->string('vat_code')->nullable();                                             // partita iva
            $table->string('address')->nullable();                                              // indirizzo
            $table->string('civic_number')->nullable();                                         // numero civico
            $table->string('zip_code')->nullable();                                             // cap
            $table->string('city')->nullable();                                                 // città
            $table->string('province')->nullable();                                             // provincia
            $table->string('country')->nullable();                                              // stato
            $table->string('rea_office')->nullable();                                           // ufficio iscrizione REA
            $table->string('rea_number')->nullable();                                           // numero iscrizione REA
            $table->string('capital')->nullable();                                              // capitale sociale
            $table->string('sole_share')->nullable();                                           // socio unico
            $table->string('liquidation_status')->nullable();                                   // stati liquidazione
            $table->string('phone')->nullable();                                                // telefono
            $table->string('fax')->nullable();                                                // fax
            $table->string('email')->nullable();                                                // email
            $table->string('pec')->nullable();                                                  // pec
            $table->string('bank')->nullable();                                                 // nome banca
            $table->string('iban')->nullable();                                                 // codice iban
            $table->string('bic')->nullable();                                                  // codice bic
            $table->string('swift')->nullable();                                                // codice swift
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('suppliers');
        Schema::enableForeignKeyConstraints();
    }
};
