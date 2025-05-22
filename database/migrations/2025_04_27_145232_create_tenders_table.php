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
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');
            $table->foreignId('client_id')->constrained()->onUpdate('cascade');
            // $table->bigInteger('container_id')->nullable();

            $table->unsignedBigInteger('container_id')->nullable();
            $table->foreign('container_id')->references('id')->on('containers');
            // $table->foreignId('container_id')->constrained()->onUpdate('cascade')->nullable();
            $table->string('tax_type');
            $table->string('type');
            $table->string('office_code');
            $table->string('office_name');
            $table->date('date')->nullable();
            $table->string('cig_code')->nullable();
            $table->string('cup_code')->nullable();
            $table->string('rdo_code')->nullable();
            $table->string('reference_code')->nullable();

            $table->timestamps();

            // $table->foreign('city_code')->references('code')->on('cities');
        });


        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');
            $table->foreignId('client_id')->constrained()->onUpdate('cascade');
            // $table->bigInteger('container_id')->nullable();

            $table->unsignedBigInteger('container_id')->nullable();
            $table->foreign('container_id')->references('id')->on('containers');
            // $table->foreignId('container_id')->constrained()->onUpdate('cascade');
            $table->string('tax_type');
            $table->string('type')->nullable();
            $table->date('validity_date')->nullable();
            $table->string('number')->nullable();
            $table->date('contract_date')->nullable();
            $table->timestamps();

            // $table->foreign('city_code')->references('code')->on('cities');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('tenders');
        Schema::enableForeignKeyConstraints();
    }
};
