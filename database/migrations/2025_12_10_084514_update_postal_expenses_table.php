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
        Schema::create('send_types', function (Blueprint $table) {                                      // tabella dei tipi di spedizioni per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');             // tenant
            $table->integer('order');                                                                   // posizione
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('postal_expenses', function (Blueprint $table) {
            $table->json('send_types')->after('shipment_type_id')->nullable();
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
