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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');
            $table->string('type');
            $table->string('denomination');
            $table->string('address');
            $table->string('zip_code');
            $table->string('city_code',4);
            $table->foreign('city_code')->references('code')->on('cities');
            $table->string('tax_code')->nullable();
            $table->string('vat_code')->nullable();
            $table->string('email')->nullable();
            $table->string('ipa_code')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
