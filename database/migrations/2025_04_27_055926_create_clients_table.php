<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::create('clients', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('company_id')->constrained()->onUpdate('cascade');
        //     $table->string('type');
        //     $table->string('denomination');
        //     $table->string('address');
        //     $table->string('zip_code');                                 // se ho l'id de comune a cosa serve?
        //     $table->foreignId('city_id')->constrained()->onUpdate('cascade');
        //     $table->string('tax_code')->nullable();
        //     $table->string('vat_code')->nullable();
        //     $table->string('email')->nullable();
        //     $table->string('ipa_code')->nullable();
        //     $table->timestamps();
        // });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');    // id azienda per multi-tenancy
            $table->string('type');                                                 // tipo cliente (Enum)
            $table->string('subtype');                                              // sottotipo cliente per intestazione fattura (Enum)
            $table->string('denomination');                                         // nome cliente
            $table->string('address');                                              // indirizzo
            $table->foreignId('city_id')->constrained()->onUpdate('cascade');       // id città
            $table->string('tax_code')->nullable();                                 // codice fiscale
            $table->string('vat_code')->nullable();                                 // partita iva
            $table->string('email')->nullable();                                    // email
            $table->string('ipa_code')->nullable();                                 // codice univoco (solo per privati)
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('clients');
        Schema::enableForeignKeyConstraints();
    }
};
