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
            $table->unsignedBigInteger('state_id')->nullable()->after('tax_number');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->string('place')->nullable()->after('city_code');
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
