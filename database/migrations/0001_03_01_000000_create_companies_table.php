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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vat_number');
            $table->string('address');
            $table->string('city_code',4);
            $table->foreign('city_code')->references('code')->on('cities');
            // $table->foreignId('city_id')->constrained();
            // $table->string('province');
            $table->boolean('is_active');
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

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('companies');
        Schema::enableForeignKeyConstraints();
    }
};
