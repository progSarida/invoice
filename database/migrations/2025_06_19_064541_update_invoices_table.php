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
            $table->string('invoice_type')->nullable()->change();
            $table->string('doc_type_id')->nullable()->after('invoice_type');
            $table->string('section')->nullable()->change();
            $table->string('sectional_id')->nullable()->after('section');
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
