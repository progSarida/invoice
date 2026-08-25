<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('active_payments', function (Blueprint $table) {
            $table->string('payment_type')->after('bank_account_id')->nullable();               // enum PaymentType, come su invoices
        });

        // pagamenti già registrati: eredito il tipo di pagamento dalla fattura collegata
        DB::table('active_payments')
            ->join('invoices', 'invoices.id', '=', 'active_payments.invoice_id')
            ->whereNotNull('invoices.payment_type')
            ->update(['active_payments.payment_type' => DB::raw('invoices.payment_type')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_payments', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
