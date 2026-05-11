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
        Schema::table('shipment_types', function (Blueprint $table) {
            $table->string('notify_type')->nullable()->after('description');                                // tipo di notifica collegato
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('notify_expense')->nullable()->after('pec');                                       // flag fornitori spese di notifica
        });

        Schema::table('send_types', function (Blueprint $table) {
            $table->string('notify_type')->nullable()->after('description');                                // tipo di notifica collegato
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
