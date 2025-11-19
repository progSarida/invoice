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
        Schema::table('new_contracts', function (Blueprint $table) {
            $table->string('invoicing_cycle')->nullable()->after('amount');                             // periodicità fatturazione (Enum)
            $table->string('new_contract_copy_path')->nullable()->after('invoicing_cycle');             // percorso file scan notifica caricato
            $table->date('new_contract_copy_date')->nullable()->after('new_contract_copy_path');        // data caricamento contratto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_contracts', function (Blueprint $table) {
            $table->dropColumn('invoicing_cycle');
            $table->dropColumn('new_contract_copy_path');
        });
    }
};
