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
            $table->string('timing_type')->nullable()->after('tax_type');                                                 // tipo tempistica: contestuale, differita (Enum)
            $table->string('delivery_note')->nullable()->after('timing_type');
            $table->date('delivery_date')->nullable()->after('delivery_note');
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
