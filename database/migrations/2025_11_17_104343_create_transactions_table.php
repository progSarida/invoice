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
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // tenant
            $table->integer('order');                                                                           // posizione
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // tenant
            $table->date('date');
            $table->foreignId('instrument_id')->constrained('instruments')->onUpdate('cascade');                // id strumento della transazione
            $table->string('description')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->onUpdate('cascade');            // id cliente
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onUpdate('cascade');        // id fornitore
            $table->decimal('in_amount', 10, 2)->default(0.00);                                                 // importo entrata
            $table->decimal('out_amount', 10, 2)->default(0.00);                                                // importo uscita
            $table->decimal('progressive_balance', 10, 2)->default(0.00);                                       // saldo progressivo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
