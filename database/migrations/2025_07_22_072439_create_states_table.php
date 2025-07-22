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
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->char('alpha2', 2);
            $table->char('alpha3', 3)->nullable();
            $table->smallInteger('country_code')->nullable();
            $table->string('iso_3166_2', 20)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('sub_region', 50)->nullable();
            $table->string('intermediate_region', 50)->nullable();
            $table->smallInteger('region_code')->nullable();
            $table->smallInteger('sub_region_code')->nullable();
            $table->smallInteger('intermediate_region_code')->nullable();
            $table->unique('alpha2', 'uk_alpha2');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('states');
        Schema::enableForeignKeyConstraints();
    }
};
