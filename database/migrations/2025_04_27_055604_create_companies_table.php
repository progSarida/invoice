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
        // Schema::create('companies', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->string('vat_number');
        //     $table->string('address');
        //     $table->string('city_code',4);
        //     $table->foreign('city_code')->references('code')->on('cities');
        //     // $table->foreignId('city_id')->constrained();
        //     // $table->string('province');
        //     $table->boolean('is_active');
        //     $table->timestamps();
        // });

        Schema::create('companies', function (Blueprint $table) {
            // già presenti
            $table->id();
            $table->string('name');                                             // nome
            $table->string('vat_number');                                       // partita iva
            $table->string('address');                                          // indirizzo
            $table->string('city_code',4);                                      // codice catastale
            $table->foreign('city_code')->references('code')->on('cities');
            $table->boolean('is_active');

            // nuovi
            $table->string('email');                                            // email
            $table->string('phone');                                            // telefono
            $table->string('fax');                                              // fax

            $table->string('tax_number');                                       // codice fiscale

            $table->string('register');                                         // albo professionale di iscrizione
            $table->foreignId('register_province_id')->constrained(             // id provincia albo professionale
                table: 'provinces', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('register_number');                                  // numero iscrizione albo professionale
            $table->date('register_date');                                      // data iscrizione albo professionale

            $table->foreignId('rea_province_id')->constrained(                  // id provincia ufficio rea
                table: 'provinces', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('rea_number');                                       // numero iscrizione REA

            $table->string('nominal_capital');                                  // capitale sociale

            $table->string('shareholders');                                     // situazione soci (Enum)

            $table->string('liquidation');                                      // stato liquidazione (Enum)


            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda per multi-tenancy
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');                                              // nome banca
            $table->string('iban');                                              // iban conto
            $table->string('bic');                                               // codice bic (swift)
            $table->timestamps();
        });

        Schema::create('curators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda per multi-tenancy
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');                                              // nome
            $table->string('surname');                                           // cognome
            $table->string('tax_code');                                          // codice fiscale
            $table->string('email');                                             // email
            $table->string('pec');                                               // pec
            $table->timestamps();
        });

        Schema::create('productors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda per multi-tenancy
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');                                              // nome
            $table->string('surname');                                           // cognome
            $table->string('tax_code');                                          // codice fiscale
            $table->string('email');                                             // email
            $table->string('pec');                                               // pec
            $table->timestamps();
        });

        Schema::create('sectionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda per multi-tenancy
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('description');                                       // descrizione (sigla)
            $table->string('client_type');                                       // tipo cliente (Enum)
            $table->string('doc_type');                                          // tipo documento (Enum)
            $table->string('numeration');                                        // numerazione (Enum)
            $table->string('progressive');                                       // numero progressivo nella numerazione
            $table->timestamps();
        });

        Schema::create('fiscal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda per multi-tenancy
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('tax_regime');                                        // regime fiscale (Enum)
            $table->string('vat_enforce');                                       // esigibilità iva (Enum)
            $table->timestamps();
        });

        Schema::create('social_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda per multi-tenancy
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('fund');                                              // tipo cassa previdenziale (Enum)
            $table->string('rate');                                              // aliquota fiscale
            $table->string('vat_code');                                          // codice iva (Enum)
            $table->timestamps();
        });

        Schema::create('withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('withholding_type');                                  // tipo ritenuta (Enum)
            $table->string('rate');                                              // aliquota fiscale
            $table->string('payment_reason');                                    // causale pagamento (Enum)
            $table->timestamps();
        });

        Schema::create('stamp_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained(                        // id azienda
                table: 'companies', indexName: 'id'
            )->onUpdate('cascade')->onDelete('cascade');
            $table->string('active');                                            // imposta di bollo automatica in fattura
            $table->string('add_row');                                           // addebito al cliente con riga aggiuntiva in fattura
            $table->string('row_description');                                   // descrizione riga da aggiungere
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('stamp_duties');
        Schema::dropIfExists('withholdings');
        Schema::dropIfExists('social_contributions');
        Schema::dropIfExists('fiscal_profiles');
        Schema::dropIfExists('sectionals');
        Schema::dropIfExists('productors');
        Schema::dropIfExists('curators');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('companies');
        Schema::enableForeignKeyConstraints();
    }
};
