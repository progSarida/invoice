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
        Schema::create('pi_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                         // tenant
            $table->integer('order');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('pi_validation_status');
            $table->timestamps();
        });

        Schema::table('passive_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('pi_validation_id')->nullable()->after('iban');                              // id validazione fattura passiva
            $table->foreign('pi_validation_id')->references('id')->on('pi_validations')
                ->onUpdate('cascade');
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
