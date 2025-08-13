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
            $table->boolean('reinvoice')->default(0)->after('payment_type');                            // rifatturazione spese postali del contratto
        });

        Schema::table('invoices',function (Blueprint $table){
            $table->unsignedBigInteger('user_id')->nullable();                                          //
            $table->foreign('user_id')->references('id')->on('users');                                  // fattura emessa da
        });

        Schema::create('shipment_types', function (Blueprint $table) {                                  // tabella dei tipi di spedizioni per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');             // tenant
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('act_types', function (Blueprint $table) {                                       // tabella dei tipi di atto per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');             // tenant
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('postal_expenses', function (Blueprint $table) {                         		// tabella delle spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');     		// tenant

            $table->string('notify_type');                                                      		// enum tipo notifica: NotifyType

            $table->foreignId('new_contract_id')->constrained('new_contracts')->onUpdate('cascade');	// contratto

            // MODALITA' DI SPEDIZIONE DELLA RICHIESTA DI LAVORAZIONE/NOTIFICA
            // Riferimenti all'entrata e alla classificazione dell'atto inviato in lavorazione/notifica
            $table->string('send_protocol_number')->nullable();                                 		// numero protocollo invio
            $table->date('send_protocol_date')->nullable();                                     		// data protocollo invio

            $table->unsignedBigInteger('shipment_type_id')->nullable();                         		//
            $table->foreign('shipment_type_id')->references('id')->on('shipment_types');        		// tipo di spedizione

            $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade');        		// cliente

            $table->unsignedBigInteger('supplier_id')->nullable();                              		//
            $table->foreign('supplier_id')->references('id')->on('suppliers');                  		// fornitore (nascondere se messo)

            $table->string('supplier')->nullable();                                            			// fornitore (nascondere se spedizione)

            $table->string('recipient')->nullable();                                            		// destinatario

            $table->string('tax_type');                                                         		// enum tipo entrata: TaxType

            $table->integer('year')->nullable();                                                		// anno

            $table->foreign('act_type_id')->references('id')->on('act_types');                  		// tipo atto
            $table->string('act_id')->nullable();                                               		// id atto
            $table->integer('act_year')->nullable();                                            		// anno atto

            $table->unsignedBigInteger('shipment_insert_user_id')->nullable();                  		//
            $table->foreign('shipment_insert_user_id')->references('id')->on('users');          		// inserita spedizione da utente

            //LAVORAZIONE DELL'ATTO RESTITUITO POST LAVORAZIONE/NOTIFICA
            // Riferimenti alla lavorazione/notifica richiesta ed effettuata dal fornitore incaricato
            $table->string('order_rif')->nullable();                                            		// riferimento commessa
            $table->string('list_rif')->nullable();                                             		// riferimento distinta

            $table->string('receive_protocol_number')->nullable();                              		// numero protocollo ricezione
            $table->date('receive_protocol_date')->nullable();                                  		// data protocollo ricezione

            $table->date('shipment_date')->nullable();                                          		// spedito in data
            $table->string('month')->nullable();                                                		// enum mese: Month

            $table->date('scan_date')->nullable();                                       				// scansionato e importato in data

            $table->date('amount_registration_date')->nullable();                               		// importo registrato in data

            $table->string('attachment')->nullable();                                           		// allegato

            $table->date('notify_insert_date')->nullable();                               				// notifica inserita in data

            $table->unsignedBigInteger('notify_insert_user_id')->nullable();              				//
            $table->foreign('notify_insert_user_id')->references('id')->on('users');      				// notifica inserita da utente

            // Riferimento alle spese della lavorazione/notifica richiesta
            $table->string('expense_type');                                                     		// enum tipo entrata: ExpenseType

            $table->unsignedBigInteger('passive_invoice_id')->nullable();              				    //
            $table->foreign('passive_invoice_id')->references('id')->on('passive_invoices');      		// notifica inserita da utente (nascondere se messo)

            $table->decimal('notify_expense_amount',10,2)->nullable();                          		// importo spese notifica (da passive_invoice_id, se spedizione)

            $table->decimal('mark_expense_amount',10,2)->nullable();                            		// importo spese del contrassegno (da passive_invoice_id, se spedizione)

            $table->string('iban')->nullable();                                                 		// iban (da passive_invoice_id, se spedizione)

            $table->string('shipment_doc_type');														// enum tipo documento: ShipmentDocType (fattura, doc da messo)

            $table->string('shipment_doc_number');														// numero documento (da passive_invoice_id, se spedizione)

            $table->string('shipment_doc_date');														// data documento (da passive_invoice_id, se spedizione)

            $table->boolean('reinvoice')->default(0);                                           		// bool per rifatturazione spese (da new_contract_id)

            // PAGAMENTO DELLA LAVORAZIONE/NOTIFICA RICHIESTA AL FORNITORE
            // Estremi del pagamento
            $table->boolean('payed')->default(0);                                               		// bool per spese pagate

            $table->date('payment_date')->nullable();                                           		// pagato in data (da passive_invoice_id, se spedizione; in caso di più pagamenti data ultimo pagamento)

            $table->decimal('payment_amount',10,2)->nullable();                                 		// totale (da passive_invoice_id, se spedizione)

            $table->date('payment_insert_date')->nullable();                                    		// pagamento inserito in data (da passive_invoice_id, se spedizione; in caso di più pagamenti data ultimo pagamento)

            $table->unsignedBigInteger('payment_insert_user_id')->nullable();                   		//
            $table->foreign('payment_insert_user_id')->references('id')->on('users');           		// pagamento inserito da utente

            // RIFATTURAZIONE DELLE SPESE DELLA LAVORAZIONE/NOTIFICA AL FORNITORE
            // Estremi della rifatturazione delle spese della alvorazione/notifica
            $table->foreignId('reinvoice_id')->constrained('invoices')->onUpdate('cascade');			// fattura -> numero, data, importo

            $table->date('reinvoice_insert_date');                                              		// data inserimento rifatturazione
            $table->unsignedBigInteger('reinvoice_insert_user_id')->nullable();                 		//
            $table->foreign('reinvoice_insert_user_id')->references('id')->on('users');         		// utente inserimento rifatturazione

            // REGISTRAZIONE DELLA DATA DI LAVORAZIONE/NOTIFICA
            $table->date('registration_date')->nullable();                                      		// data notifica registrata in data

            $table->unsignedBigInteger('registration_user_id')->nullable();                     		//
            $table->foreign('registration_user_id')->references('id')->on('users');             		// datanotifica registrata da utente

            //NOTE
            $table->string('note')->nullable();                                                 		// note

            $table->timestamps();
        });

        // Schema::create('postal_expenses', function (Blueprint $table) {                                         // tabella delle spese postali
        //     $table->id();

        // // campi comuni
        //     $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // tenant
        //     $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade');                        // cliente
        //     $table->string('notify_type');                                                                      // enum tipo notifica: NotifyType
        //     $table->foreignId('new_contract_id')->constrained('new_contracts')->onUpdate('cascade');            // contratto
        //     $table->string('tax_type');                                                                         // enum tipo entrata: TaxType
        //     $table->boolean('reinvoice')->default(0);                                                           // bool per rifatturazione spese
        //     $table->string('order_rif')->nullable();                                                            // riferimento commessa
        //     $table->string('list_rif')->nullable();                                                             // riferimento distinta

        // // campi usati nel caso di notify_type == 'spedizione'
        //     $table->date('s_shipment_date')->nullable();                                                        // spedito in data
        //     $table->string('s_month')->nullable();                                                              // enum mese: Month

        //     $table->unsignedBigInteger('s_shipment_type_id')->nullable();                                       //
        //     $table->foreign('s_shipment_type_id')->references('id')->on('shipment_types');                      // tipo di spedizione
        //     // $table->foreignId('s_shipment_type_id')->constrained('shipment_types')->onUpdate('cascade');        // tipo di spedizione

        //     $table->unsignedBigInteger('s_supplier_id')->nullable();                                            //
        //     $table->foreign('s_supplier_id')->references('id')->on('suppliers');                                // fornitore
        //     // $table->foreignId('s_supplier_id')->constrained('suppliers')->onUpdate('cascade');                  // fornitore

        //     $table->integer('s_year')->nullable();                                                                          // anno
        //     $table->string('s_postal_doc_type')->nullable();                                                    // enum tipo documento inviato: PostalDocType
        //     $table->string('s_product_type')->nullable();                                                       // enum tipo spesa: ProductType
        //     $table->decimal('s_amount',10,2)->nullable();                                                       // importo

        //     $table->unsignedBigInteger('s_passive_invoice_id')->nullable();                                     //
        //     $table->foreign('s_passive_invoice_id')->references('id')->on('passive_invoices');                  // fattura passiva da rifatturare
        //     // $table->foreignId('s_passive_invoice_id')->constrained('passive_invoices')->onUpdate('cascade');    // fattura passiva da rifatturare

        //     $table->decimal('s_passive_invoice_expenses',10,2)->nullable();                                     // spese postali
        //     $table->date('s_passive_invoice_settle_date')->nullable();                                          // saldato in data
        //     $table->decimal('s_passive_invoice_amount',10,2)->nullable();                                       // totale

        // // campi usati nel caso di notify_Type == 'messo'
        //     $table->date('m_notify_registration_date')->nullable();                                             // notifica registrata in data

        //     $table->unsignedBigInteger('m_notify_registration_user_id')->nullable();                            //
        //     $table->foreign('m_notify_registration_user_id')->references('id')->on('users');                    // notifica registrata da utente
        //     // $table->foreignId('m_notify_registration_user_id')->constrained('users')->onUpdate('cascade');      // notifica registrata da utente

        //     $table->date('m_scan_import_date')->nullable();                                                     // scansionato e importato in data

        //     $table->unsignedBigInteger('m_scan_import_user_id')->nullable();                                    //
        //     $table->foreign('m_scan_import_user_id')->references('id')->on('users');                            // scansionato e importato da utente
        //     // $table->foreignId('m_scan_import_user_id')->constrained('users')->onUpdate('cascade');           // scansionato e importato da utente

        //     $table->string('m_send_protocol_number')->nullable();                                               // numero protocollo invio
        //     $table->date('m_send_protocol_date')->nullable();                                                   // data protocollo invio
        //     $table->string('m_receive_protocol_number')->nullable();                                            // numero protocollo ricezione
        //     $table->date('m_receive_protocol_date')->nullable();                                                // data protocollo ricezione
        //     $table->string('m_supplier')->nullable();                                                           // ente da rimborsare
        //     $table->unsignedBigInteger('m_act_type_id')->nullable();                                            //
        //     $table->foreign('m_act_type_id')->references('id')->on('act_types');                                // tipo atto
        //     $table->string('m_act_id')->nullable();                                                             // id atto
        //     $table->integer('m_act_year')->nullable();                                                          // anno atto
        //     $table->string('m_recipient')->nullable();                                                          // destinatario
        //     $table->decimal('m_amount',10,2)->nullable();                                                       // importo
        //     $table->string('m_iban')->nullable();                                                               // iban
        //     $table->string('attachment')->nullable();                                                           // allegato
        //     $table->boolean('m_payed')->default(0);                                                             // bool pagato
        //     $table->date('m_payment_date')->nullable();                                                         // pagato in data
        //     $table->date('m_payment_insert_date')->nullable();                                                  // pagamento inserito in data

        //     $table->unsignedBigInteger('m_payment_insert_user_id')->nullable();                                 //
        //     $table->foreign('m_payment_insert_user_id')->references('id')->on('users');                         // inserito pagamento da utente
        //     // $table->foreignId('m_payment_insert_user_id')->constrained('users')->onUpdate('cascade');           // inserito pagamento da utente

        // // altri campi comuni
        //     $table->date('reinvoice_insert_date');                                                              // rifatturato da utente
        //     $table->foreignId('reinvoice_insert_user_id')->constrained('users')->onUpdate('cascade');           // rifatturato in data
        //     $table->foreignId('reinvoice_id')->constrained('invoices')->onUpdate('cascade');                    // fattura
        //     $table->string('note')->nullable();                                                                 // note




        //     // $table->foreignId('company_id')->constrained()->onUpdate('cascade');

        //     // $table->foreignId('new_contract_id')->constrained()->onUpdate('cascade');

        //     // $table->string('send_number');
        //     // $table->string('send_date');
        //     // // $table->string('shipment_type_id');                                                  // in sospeso

        //     // $table->foreignId('supplier_id')->constrained('suppliers')->onUpdate('cascade');
        //     // $table->string('recipient');
        //     // $table->string('tax_type');
        //     // $table->integer('year');
        //     // // $table->foreignId('act_type_id')->constrained()->onUpdate('cascade');               // in sospeso
        //     // $table->string('act_id');
        //     // $table->integer('act_year');
        //     // $table->foreignId('shipment_insert_user_id')->constrained('users')->onUpdate('cascade');

        //     // $table->string('order_rif');
        //     // $table->string('list_rif');
        //     // $table->string('receive_number');
        //     // $table->date('receive_date');
        //     // $table->date('date');
        //     // $table->string('month');
        //     // $table->date('scan_date');
        //     // $table->date('registration_date');
        //     // $table->string('attachment');
        //     // $table->foreignId('notify_insert_user_id')->constrained('users')->onUpdate('cascade');

        //     // $table->string('expense_type');
        //     // $table->decimal('expense_amount',10,2)->nullable();
        //     // $table->decimal('mark_expense_amount',10,2)->nullable();
        //     // $table->boolean('reinvoice')->default(0);
        //     // $table->string('iban')->nullable();
        //     // $table->string('doc_type_id');
        //     // $table->string('doc_number');
        //     // $table->date('doc_date');

        //     // $table->boolean('payed')->default(0);
        //     // $table->json('payment_dates');
        //     // $table->date('payment_last_date');
        //     // $table->decimal('payment_total',10,2)->nullable();
        //     // $table->foreignId('payment_insert_user_id')->constrained('users')->onUpdate('cascade');
        //     // $table->date('payment_insert_date');

        //     // $table->date('send_reinvoice_date');
        //     // $table->string('reinvoice_number');
        //     // $table->date('reinvoice_date');
        //     // $table->decimal('reinvoice_total',10,2)->nullable();
        //     // $table->foreignId('reinvoice_insert_user_id')->constrained('users')->onUpdate('cascade');
        //     // $table->date('reinvoice_insert_date');

        //     // $table->date('notify_date_registration_date');
        //     // $table->foreignId('notify_date_registration_user_id')->constrained('users')->onUpdate('cascade');

        //     // $table->string('note');

        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('postal_expenses');
        Schema::dropIfExists('shipment_types');
        Schema::dropIfExists('act_types');
        Schema::enableForeignKeyConstraints();
    }
};
