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
            $table->string('email')->nullable();                                            // email
            $table->string('phone')->nullable();                                            // telefono
            $table->string('fax')->nullable();                                              // fax
            $table->string('tax_number')->nullable();                                       // codice fiscale
            $table->string('register')->nullable();                                         // albo professionale di iscrizione

            $table->unsignedBigInteger('register_province_id')->nullable();
            $table->foreign('register_province_id')->references('id')->on('provinces')      // id provincia albo professionale
            ->onUpdate('cascade')->onDelete('cascade');

            $table->string('register_number')->nullable();                                  // numero iscrizione albo professionale
            $table->date('register_date')->nullable();                                      // data iscrizione albo professionale

            $table->unsignedBigInteger('rea_province_id')->nullable();
            $table->foreign('rea_province_id')->references('id')->on('provinces')           // id provincia ufficio rea
            ->onUpdate('cascade')->onDelete('cascade');

            $table->string('rea_number')->nullable();                                       // numero iscrizione REA
            $table->string('nominal_capital')->nullable();                                  // capitale sociale
            $table->string('shareholders')->nullable();                                     // situazione soci (Enum)
            $table->string('liquidation')->nullable();                                      // stato liquidazione (Enum)
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
