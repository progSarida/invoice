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
        Schema::create('active_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('amount',10,2);
            $table->date('payment_date')->nullable();
            $table->date('registration_date')->nullable();
            $table->foreignId('registration_user_id')->nullable()->constrained('users')->onUpdate('cascade');
            $table->boolean('validated')->default(0);
            $table->date('validation_date')->nullable();
            $table->foreignId('validation_user_id')->nullable()->constrained('users')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('active_payments');
        Schema::enableForeignKeyConstraints();
    }
};
