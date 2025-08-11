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
        Schema::create('postal_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');

            $table->foreignId('new_contract_id')->constrained('new_contracts')->onUpdate('cascade');

            $table->string('send_number');
            $table->string('send_date');
            // $table->string('shipment_type_id')->constrained('shipment_types')->onUpdate('cascade');      // in sospeso
            $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onUpdate('cascade');
            $table->string('recipient');
            $table->string('tax_type');
            $table->integer('year');
            // $table->foreignId('act_type_id')->constrained('act_types')->onUpdate('cascade');             // in sospeso
            $table->string('act_id');
            $table->integer('act_year');
            $table->foreignId('shipment_insert_user_id')->constrained('users')->onUpdate('cascade');

            $table->string('order_rif');
            $table->string('list_rif');
            $table->string('receive_number');
            $table->date('receive_date');
            $table->date('date');
            $table->string('month');
            $table->date('scan_date');
            $table->date('registration_date');
            $table->string('attachment');
            $table->foreignId('notify_insert_user_id')->constrained('users')->onUpdate('cascade');

            $table->string('expense_type');
            $table->decimal('expense_amount',10,2)->nullable();
            $table->decimal('mark_expense_amount',10,2)->nullable();
            $table->boolean('reinvoice')->default(0);
            $table->string('iban')->nullable();
            $table->string('doc_type_id');
            $table->string('doc_number');
            $table->date('doc_date');

            $table->boolean('payed')->default(0);
            $table->json('payment_dates');
            $table->date('payment_last_date');
            $table->decimal('payment_total',10,2)->nullable();
            $table->foreignId('payment_insert_user_id')->constrained('users')->onUpdate('cascade');
            $table->date('payment_insert_date');

            $table->date('send_reinvoice_date');
            $table->string('reinvoice_number');
            $table->date('reinvoice_date');
            $table->decimal('reinvoice_total',10,2)->nullable();
            $table->foreignId('reinvoice_insert_user_id')->constrained('users')->onUpdate('cascade');
            $table->date('reinvoice_insert_date');

            $table->date('notify_date_registration_date');
            $table->foreignId('notify_date_registration_user_id')->constrained('users')->onUpdate('cascade');

            $table->string('note');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('postal_expenses');
        Schema::enableForeignKeyConstraints();
    }
};
