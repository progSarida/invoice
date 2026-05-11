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
            $table->string('reinvoice_type')->nullable()->after('reinvoice');                                // tipo di notifica collegato
        });

        Schema::table('postal_expenses', function (Blueprint $table) {
            $table->string('reinvoice_type')->nullable()->after('reinvoice');                                // tipo di notifica collegato
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
