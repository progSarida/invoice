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
        Schema::table('bank_accounts',function (Blueprint $table){
            $table->string('holder')->after('name');
            $table->string('number')->after('holder');
            $table->string('swift')->after('bic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('holder');
            $table->dropColumn('number');
            $table->dropColumn('swift');
        });
        Schema::enableForeignKeyConstraints();
    }
};
