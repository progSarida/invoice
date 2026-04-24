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
            $table->boolean('courtesy')->after('closed')->default(0);                                                   // flag invio fattura di cortesia al cliente
            $table->string('courtesy_address')->nullable()->after('courtesy');                                          // indirizzo per l'invio del afattura di cortesia al cliente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_contracts', function (Blueprint $table) {
            $table->dropColumn('courtesy_address');
            $table->dropColumn('courtesy');
        });
    }
};
