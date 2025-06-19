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
            $table->string('phone')->nullable()->after('vat_code');
            $table->string('pec')->nullable()->after('email');
            $table->date('birth_date')->nullable()->after('city_id');
            $table->string('birth_place')->nullable()->after('birth_date');
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
