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
        Schema::table('new_contracts',function (Blueprint $table){
            // $table->boolean('reinvoice')->default(0)->after('payment_type');                            // rifatturazione spese postali del contratto
        });

        Schema::table('invoices',function (Blueprint $table){
            // $table->unsignedBigInteger('user_id')->nullable();                                          //
            // $table->foreign('user_id')->references('id')->on('users');                                  // fattura emessa da
        });

        Schema::create('shipment_types', function (Blueprint $table) {                                  // tabella delle modalità di spedizioni per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');             // tenant
            $table->integer('order');                                                                   // posizione
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('act_types', function (Blueprint $table) {                                       // tabella dei tipi di atto per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');             // tenant
            $table->integer('order');                                                                   // posizione
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('postal_expenses', function (Blueprint $table) {                         		// tabella delle spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');     		// tenant

            $table->string('notify_type');                                                      		// enum tipo notifica: NotifyType
            $table->foreignId('new_contract_id')->constrained('new_contracts')->onUpdate('cascade');	// contratto

            // Riferimenti all'entrata e alla classificazione dell'atto inviato in lavorazione/notifica
            $table->string('send_protocol_number')->nullable();                                 		// numero protocollo invio
            $table->date('send_protocol_date')->nullable();                                     		// data protocollo invio

            $table->unsignedBigInteger('shipment_type_id')->nullable();                         		//
            $table->foreign('shipment_type_id')->references('id')->on('shipment_types');        		// modalità di invio della richiesta

            $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade');        		// cliente (ente per cui è richiesta la lavorazione)

            $table->unsignedBigInteger('supplier_id')->nullable();                              		//
            $table->foreign('supplier_id')->references('id')->on('suppliers');                  		// fornitore (mostrare se spedizione)
            $table->string('supplier_name')->nullable();                                                // ente da rimborsare (mostrare se messo)

            $table->string('recipient')->nullable();                                            		// destinatario notifica/trasgressore

            $table->string('tax_type');                                                         		// enum tipo entrata: TaxType

            $table->integer('manage_year')->nullable();                                                	// anno di gestione

            $table->unsignedBigInteger('act_type_id')->nullable();                         		        //
            $table->foreign('act_type_id')->references('id')->on('act_types');                  		// tipo atto
            $table->string('act_id')->nullable();                                               		// id atto
            $table->integer('act_year')->nullable();                                            		// anno atto

            $table->string('act_attachment_path')->nullable();                                          // percorso file scan atto caricato
            $table->date('act_attachment_date')->nullable();                                  		    // data file scan atto caricato

            $table->unsignedBigInteger('shipment_insert_user_id')->nullable();                  		//
            $table->foreign('shipment_insert_user_id')->references('id')->on('users');          		// utente che ha inserito i dati precedenti
            $table->date('shipment_insert_date')->nullable();                                  		    // data inserimento dati precedenti

            // Riferimenti alla lavorazione/notifica richiesta ed effettuata dal fornitore incaricato
            $table->string('notify_attachment_path')->nullable();                                       // percorso file scan notifica caricato
            $table->date('notify_attachment_date')->nullable();                                  		// data file scan notifica caricato

            $table->string('order_rif')->nullable();                                            		// riferimento commessa
            $table->string('list_rif')->nullable();                                             		// riferimento distinta

            $table->string('receive_protocol_number')->nullable();                              		// numero protocollo ricezione
            $table->date('receive_protocol_date')->nullable();                                  		// data protocollo ricezione

            $table->integer('notify_year')->nullable();                                            		// anno notifica

            $table->string('notify_month')->nullable();                                                	// enum mese: Month

            $table->decimal('notify_amount',10,2)->nullable();                          		        // importo notifica

            $table->date('amount_registration_date')->nullable();                               		// data registrazione importo

            $table->unsignedBigInteger('notify_insert_user_id')->nullable();                  		    //
            $table->foreign('notify_insert_user_id')->references('id')->on('users');          		    // utente che ha inserito i dati precedenti
            $table->date('notify_insert_date')->nullable();                                  		    // data inserimento dati precedenti

            // Riferimento alle spese della lavorazione/notifica richiesta
            $table->string('expense_type')->nullable();                                                 // enum tipologoa spesa: ExpenseType

            $table->unsignedBigInteger('passive_invoice_id')->nullable();              				    //
            $table->foreign('passive_invoice_id')->references('id')->on('passive_invoices');      		// notifica inserita da utente (mostrare se spedizione)

            $table->decimal('notify_expense_amount',10,2)->nullable();                          		// importo spese notifica (da passive_invoice_id, se spedizione)

            $table->decimal('mark_expense_amount',10,2)->nullable();                            		// importo spese del contrassegno (da passive_invoice_id, se spedizione)

            $table->boolean('reinvoice')->default(0);                                           		// bool per rifatturazione spese (da new_contract_id)

            $table->string('shipment_doc_type')->nullable();											// enum tipo documento: ShipmentDocType (fattura, doc da messo)

            $table->string('shipment_doc_number')->nullable();											// numero documento (da passive_invoice_id, se spedizione)

            $table->string('shipment_doc_date')->nullable();											// data documento (da passive_invoice_id, se spedizione)

            $table->string('iban')->nullable();                                                 		// iban (da passive_invoice_id, se spedizione)

            $table->unsignedBigInteger('expense_insert_user_id')->nullable();                  		    //
            $table->foreign('expense_insert_user_id')->references('id')->on('users');          		    // utente che ha inserito i dati precedenti
            $table->date('expense_insert_date')->nullable();                                  		    // data inserimento dati precedenti

            // Estremi del pagamento
            $table->boolean('payed')->default(0);                                               		// bool per spese pagate

            $table->date('payment_date')->nullable();                                           		// data pagamento (da passive_invoice_id, se spedizione; in caso di più pagamenti data ultimo pagamento)

            $table->decimal('payment_total',10,2)->nullable();                                 		    // totale pagamenti (da passive_invoice_id, se spedizione)

            $table->unsignedBigInteger('payment_insert_user_id')->nullable();                   		//
            $table->foreign('payment_insert_user_id')->references('id')->on('users');           		// utente che ha inserito i dati precedenti
            $table->date('payment_insert_date')->nullable();                                    		// data inserimento dati precedenti

            // Estremi della rifatturazione delle spese della alvorazione/notifica
            $table->unsignedBigInteger('reinvoice_id')->nullable();                 		            //
            $table->foreign('reinvoice_id')->references('id')->on('invoices');                          // fattura -> numero, data, importo

            $table->string('reinvoice_number')->nullable();												// numero fattura emessa per rifatturazione (da reinvoice_id)
            $table->string('reinvoice_date')->nullable();												// data fattura emessa per rifatturazione (da reinvoice_id)
            $table->decimal('reinvoice_amount',10,2)->nullable();                            		    // importo fattura emessa per rifatturazione (da reinvoice_id)

            $table->unsignedBigInteger('reinvoice_insert_user_id')->nullable();                 		//
            $table->foreign('reinvoice_insert_user_id')->references('id')->on('users');         		// utente che ha inserito i dati precedenti
            $table->date('reinvoice_insert_date')->nullable();                                          // data inserimento dati precedenti

            // Registrazione della data di lavorazione/modifica
            $table->string('reinvoice_attachment_path')->nullable();                                    // percorso file scan fattura emessa caricato
            $table->date('reinvoice_attachment_date')->nullable();                                  	// data file scan fattura emessa caricato

            $table->date('notify_date_registration_date')->nullable();                                  // data registrazione data di notifica

            $table->unsignedBigInteger('reinvoice_registration_user_id')->nullable();                 	//
            $table->foreign('reinvoice_registration_user_id')->references('id')->on('users');         	// utente che ha inserito i dati precedenti
            $table->date('reinvoice_registration_date')->nullable();                                    // data inserimento dati precede

            //Note
            $table->string('note')->nullable();                                                 		// note

            $table->timestamps();
        });

        Schema::table('invoice_items',function (Blueprint $table){
            $table->unsignedBigInteger('postal_expense_id')->nullable()->after('is_with_vat');          //
            $table->foreign('postal_expense_id')->references('id')->on('postal_expenses');              // riferimento spesa di notifica
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        // Schema::table('new_contracts', function (Blueprint $table) {
        //     $table->dropColumn('reinvoice');                                                            // rimuovo il campo 'reinvoice' dalla tabella 'new_contracts'
        // });
        // Schema::table('invoices', function (Blueprint $table) {
        //     $table->dropForeign(['user_id']);                                                           //
        //     $table->dropColumn('user_id');                                                              // rimuovo la chiave esterna e il campo 'user_id' dalla tabella 'invoices'
        // });
        Schema::dropIfExists('postal_expenses');
        Schema::dropIfExists('shipment_types');
        Schema::dropIfExists('act_types');
        Schema::enableForeignKeyConstraints();
    }
};
