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
            $table->string('doc_type');                                                         // codice tipo di documento
            $table->date('invoice_date');                                                       // data fattura
            $table->string('number');                                                           // numero fattura
            $table->string('description');                                                      // descrizione
            $table->decimal('total',10,2);                                                      // totale fattura
            $table->string('payment_term');                                                     // condizioni di pagamento
            $table->string('payment_method');                                                   // metodo di pagamento
            $table->date('payment_deadline');                                                   // scadenza pagamento
            $table->string('filename');                                                         // nome file associati
            $table->string('xml_path');                                                         // percorso file xml
            $table->string('pdf_path');                                                         // percorso file pdf
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
