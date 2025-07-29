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
        Schema::create('passive_payments', function (Blueprint $table) {                                        // tabella pagamenti passivi
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade')->onDelete('cascade');           // id tenant
            $table->foreignId('invoice_id')->constrained()->onUpdate('cascade')->onDelete('cascade');           // id fattura passiva
            $table->decimal('amount',10,2);                                                                     // importo pagamento
            $table->date('payment_date')->nullable();                                                           // data pagamento
            $table->date('registration_date')->nullable();                                                      // data registrazione
            $table->foreignId('registration_user_id')->nullable()->constrained('users')->onUpdate('cascade');   // registrato da
            $table->boolean('validated')->default(0);                                                           // validazione pagamento
            $table->date('validation_date')->nullable();                                                        // data validazione
            $table->foreignId('validation_user_id')->nullable()->constrained('users')->onUpdate('cascade');     // validato da
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('passive_payments');
        Schema::enableForeignKeyConstraints();
    }
};
