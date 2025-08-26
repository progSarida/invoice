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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_reference')->nullable()->after('amount');                             // periodicità fatturazione (Enum)
            $table->date('reference_date_from')->nullable();                                  		      // data inizio periodo di fatturazione
            $table->date('reference_date_to')->nullable();                                  		      // data fine periodo di fatturazione
            $table->integer('reference_number_from')->nullable();                                         // primo verbale fatturato
            $table->integer('reference_number_to')->nullable();                                           // ultimo verbale fatturato
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('reference_date_from');
            $table->dropColumn('reference_date_from');
            $table->dropColumn('reference_date_to');
            $table->dropColumn('reference_number_from');
            $table->dropColumn('reference_number_to');
        });
    }
};
