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
        Schema::table('clients',function (Blueprint $table){
            $table->unsignedBigInteger('state_id')->nullable()->after('denomination');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->string('place')->nullable()->after('city_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->unsignedBigInteger('city_id')->nullable()->change();
            $table->foreign('city_id')->references('id')->on('cities');
            $table->string('zip_code')->nullable()->change();
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
