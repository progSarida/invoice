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
        Schema::create('curators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')            // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('name')->nullable();                                  // nome
            $table->string('surname')->nullable();                               // cognome
            $table->string('tax_code')->nullable();                              // codice fiscale
            $table->string('email')->nullable();                                 // email
            $table->string('pec')->nullable();                                   // pec
            $table->timestamps();
        });

        Schema::create('productors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')            // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('name')->nullable();                                  // nome
            $table->string('surname')->nullable();                               // cognome
            $table->string('tax_code')->nullable();                              // codice fiscale
            $table->string('email')->nullable();                                 // email
            $table->string('pec')->nullable();                                   // pec
            $table->timestamps();
        });

        Schema::create('sectionals', function (Blueprint $table) {
            $table->id();
           $table->foreignId('company_id')->constrained('companies')             // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('description');                                       // descrizione (sigla)
            $table->string('client_type');                                       // tipo cliente (Enum)
            $table->string('doc_type');                                          // tipo documento (Enum)
            $table->string('numeration_type');                                   // numerazione (Enum)
            $table->string('progressive');                                       // numero progressivo nella numerazione
            $table->timestamps();
        });

        Schema::create('fiscal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')            // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('tax_regime')->nullable();                            // regime fiscale (Enum)
            $table->boolean('vat_enforce')->nullable();                          // esigibilità iva
            $table->string('vat_enforce_type')->nullable();                      // esigibilità iva (Enum)
            $table->timestamps();
        });

        Schema::create('social_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')            // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('fund');                                              // tipo cassa previdenziale (Enum)
            $table->integer('rate');                                             // aliquota cassa
            $table->integer('taxable_perc ');                                    // percentuale imponibile
            $table->string('vat_code');                                          // codice iva (Enum)
            $table->timestamps();
        });

        Schema::create('withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')            // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('withholding_type');                                  // tipo ritenuta (Enum)
            $table->integer('rate');                                             // aliquota fiscale
            $table->integer('taxable_perc ');                                    // percentuale imponibile
            $table->string('payment_reason');                                    // causale pagamento (Enum)
            $table->timestamps();
        });

        Schema::create('stamp_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')             // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->boolean('active')->default(false);                            // imposta di bollo automatica in fattura
            $table->decimal('value',10,2)->nullable();                            // aliquota fiscale
            $table->boolean('add_row')->default(false);                           // addebito al cliente con riga aggiuntiva in fattura
            $table->string('row_description')->nullable();                        // descrizione riga da aggiungere
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curators');
        Schema::dropIfExists('productors');
        Schema::dropIfExists('sectionals');
        Schema::dropIfExists('fiscal_profiles');
        Schema::dropIfExists('social_contributions');
        Schema::dropIfExists('withholdings');
        Schema::dropIfExists('stamp_duties');
    }
};
