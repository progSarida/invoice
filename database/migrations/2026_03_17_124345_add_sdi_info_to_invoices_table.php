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
            $table->text('sdi_info')->after('sdi_status')->nullable();
        });

        Schema::table('sdi_notifications', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sdi_notifications', function (Blueprint $table) {
            //
        });

        Schema::table('invoices', function (Blueprint $table) {
            //
        });
    }
};
