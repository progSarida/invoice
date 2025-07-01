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
        Schema::create('new_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()                          // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('client_id')->constrained()                           // id cliente
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('tax_type');                                             // tipo entrata (Enum)
            $table->date('start_validity_date')->nullable();                        // data inizio validità contratto
            $table->date('end_validity_date')->nullable();                          // data fine validità contratto

            $table->unsignedBigInteger('accrual_type_id')->nullable();
            $table->foreign('accrual_type_id')->references('id')->on('accrual_types')
            ->onUpdate('cascade')->onDelete('cascade');
            // $table->string('accrual_type_id')->nullable();                          // id competenza
            // $table->foreignId('accrual_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');

            $table->string('payment_type')->nullable();                             // tipo pagamento (Enum)
            $table->string('cig_code');                                             // codice identificativo gara
            $table->string('cup_code');                                             // codice unico progetto
            $table->string('office_code');                                          // codice ufficio a cui inviare la fattura (vedere tabella portale IPA)
            $table->string('office_name');                                          // nome ufficio a cui inviare la fattura (vedere tabella portale IPA)
            $table->decimal('amount',10,2);                                         // importo
            $table->timestamps();
        });

        Schema::create('contract_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id')->nullable();                  // id contratto
            $table->foreign('contract_id')->references('id')->on('new_contracts')   //
                ->onUpdate('cascade')->onDelete('cascade');                         //
            $table->string('number');                                               // numero del contratto
            $table->string('contract_type');                                        // tipo contratto (Enum)
            $table->date('date')->nullable();                                       // data contratto
            $table->string('description');                                          // descrizione contratto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contract_details');
        Schema::dropIfExists('new_contracts');
        Schema::enableForeignKeyConstraints();
    }
};
