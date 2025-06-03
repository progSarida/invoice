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
