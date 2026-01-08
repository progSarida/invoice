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
            $table->json('manage_types')->after('accrual_types')->nullable();                               // servizi associati al contratto
            $table->boolean('closed')->after('invoicing_cycle')->nullable();                                // flag chiusura contratto
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
