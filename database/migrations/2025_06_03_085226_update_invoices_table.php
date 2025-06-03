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
            $table->string('flow');                                                 // tipo fattura: in => passiva, out => attiva (Enum)
            $table->string('accrual_type_id');                                      // id tipo di competenza
            $table->string('manage_type_id')->nullable();                           // id tipo di gestione
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
