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
        Schema::table('active_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->after('payment_date')->nullable()->constrained();
        });

        Schema::table('passive_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->after('iban')->nullable()->constrained();
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
