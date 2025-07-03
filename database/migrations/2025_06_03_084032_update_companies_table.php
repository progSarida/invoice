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
        Schema::table('companies',function (Blueprint $table){
            $table->string('phone')->nullable()->after('city_code');                            // telefono
            $table->string('email')->nullable()->after('phone');                                // email
            $table->string('fax')->nullable()->after('email');                                  // fax
            $table->string('tax_number')->nullable()->after('vat_number');                      // codice fiscale

            $table->string('register')->nullable()->after('fax');                               // albo professionale di iscrizione
            $table->unsignedBigInteger('register_province_id')->nullable()->after('register');
            $table->foreign('register_province_id')->references('id')->on('provinces')          // id provincia albo professionale
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('register_number')->nullable()->after('register_province_id');                                      // numero iscrizione albo professionale
            $table->date('register_date')->nullable()->after('register_number');                                          // data iscrizione albo professionale

            $table->unsignedBigInteger('rea_province_id')->nullable()->after('register_date');
            $table->foreign('rea_province_id')->references('id')->on('provinces')               // id provincia ufficio rea
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('rea_number')->nullable()->after('rea_province_id');                 // numero iscrizione REA
            $table->string('nominal_capital')->nullable()->after('rea_number');                 // capitale sociale
            $table->string('shareholders')->nullable()->after('nominal_capital');               // situazione soci (Enum)
            $table->string('liquidation')->nullable()->after('shareholders');                   // stato liquidazione (Enum)
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
