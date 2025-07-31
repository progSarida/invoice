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
        Schema::create('passive_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');                // id tenant
            $table->foreignId('supplier_id')->constrained()->onUpdate('cascade');               // id fornitore
            $table->string('doc_type')->nullable();                                             // codice tipo di documento
            $table->date('invoice_date')->nullable();                                           // data fattura
            $table->string('number')->nullable();                                               // numero fattura
            $table->string('description')->nullable();                                          // descrizione
            $table->decimal('total',10,2)->nullable();                                          // totale fattura
            $table->string('payment_term')->nullable();                                         // condizioni di pagamento
            $table->string('payment_method')->nullable();                                       // metodo di pagamento
            $table->date('payment_deadline')->nullable();                                       // scadenza pagamento
            $table->string('bank')->nullable();                                                 // nome banca
            $table->string('iban')->nullable();                                                 // codice iban
            $table->string('filename')->nullable();                                             // nome file associati
            $table->string('xml_path')->nullable();                                             // percorso file xml
            $table->string('pdf_path')->nullable();                                             // percorso file pdf
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('passive_invoices');
        Schema::enableForeignKeyConstraints();
    }
};
