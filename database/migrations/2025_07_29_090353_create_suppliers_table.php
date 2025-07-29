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
            $table->string('type')->nullable();                                                 // tipo fornitore
            $table->string('subtype')->nullable();                                              // sottotipo fornitore
            $table->string('denomination');                                                     // nome fornitore
            $table->unsignedBigInteger('state_id')->nullable();                                 // id stato
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();        //
            $table->string('address')->nullable();                                              // indirizzo
            $table->string('zip_code')->nullable();                                             // cap
            $table->unsignedBigInteger('city_id')->nullable();                                  // id città (in caso di indirizzo italiano)
            $table->foreign('city_id')->references('id')->on('cities');                         //
            $table->string('place')->nullable();                                                // città (in caso di indirizzo estero)
            $table->date('birth_date')->nullable();                                             // data di nascita
            $table->string('birth_place')->nullable();                                          // luogo di nascita
            $table->string('tax_code')->nullable();                                             // codice fiscale
            $table->string('vat_code')->nullable();                                             // partita iva
            $table->string('phone')->nullable();                                                // telefono
            $table->string('email')->nullable();                                                // email
            $table->string('pec')->nullable();                                                  // pec
            $table->string('ipa_code')->nullable();                                             // codic ipa
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
