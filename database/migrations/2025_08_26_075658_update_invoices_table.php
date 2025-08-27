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
            $table->string('invoice_reference')->nullable()->after('manage_type_id');                   // periodicità fatturazione (Enum)
            $table->date('reference_date_from')->nullable()->after('invoice_reference');                // data inizio periodo di fatturazione
            $table->date('reference_date_to')->nullable()->after('reference_date_from');                // data fine periodo di fatturazione
            $table->integer('reference_number_from')->nullable()->after('reference_date_to');           // primo verbale fatturato
            $table->integer('reference_number_to')->nullable()->after('reference_number_from');         // ultimo verbale fatturato
            $table->integer('total_number')->nullable()->after('reference_number_to');                  // numero totale verbali fatturati
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
            $table->dropColumn('total_number');
        });
    }
};
