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
        Schema::table('invoices',function (Blueprint $table){
            $table->unsignedBigInteger('contract_id')->nullable()->after('container_id');
            $table->foreign('contract_id')->references('id')->on('new_contracts')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('contract_id');
        });
        Schema::enableForeignKeyConstraints();
    }
};
