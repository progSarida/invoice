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
        Schema::create('bails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');     // tenant

            $table->unsignedBigInteger('client_id')->nullable();                                // cliente
            $table->foreign('client_id')->references('id')->on('clients')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedBigInteger('contract_id')->nullable();                              // contratto
            $table->foreign('contract_id')->references('id')->on('new_contracts')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->string('cig_code')->nullable();                                             // codice identificativo gara
            $table->string('tax_type')->nullable();                                             // tipo entrata (Enum)
            $table->string('insurance')->nullable();                                            // assicurazione
            $table->string('agency')->nullable();                                               // agenzia
            $table->string('bill_number')->nullable();                                          // numero polizza
            $table->date('bill_date')->nullable();                                              // data polizza
            $table->string('bill_attachment_path')->nullable();                                 // percorso file polizza
            $table->date('bill_start')->nullable();                                             // inizio polizza
            $table->date('bill_deadline')->nullable();                                          // scadenza polizza
            $table->string('year_duration')->nullable();                                        // anni polizza
            $table->string('month_duration')->nullable();                                       // mesi polizza
            $table->string('day_duration')->nullable();                                         // giorni polizza
            $table->decimal('original_premium',10,2)->nullable();                               // premio originale
            $table->date('original_pay_date')->nullable();                                      // data pagamento premio originario
            $table->string('bail_status')->nullable();                                          // stato cauzione (Enum)
            $table->date('release_date')->nullable();                                           // scadenza polizza
            $table->decimal('renew_premium',10,2)->nullable();                                  // premio rinnovo
            $table->date('renew_date')->nullable();                                             // data rinnovo
            $table->string('receipt_attachment_path')->nullable();                              // percorso file ricevuta di quietanza
            $table->string('note')->nullable();                                                 // note
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bails');
    }
};
