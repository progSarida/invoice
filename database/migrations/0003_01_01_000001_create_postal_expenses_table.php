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
            $table->boolean('reinvoice')->default(0)->after('payment_type');                                    // rifatturazione spese postali del contratto
        });

        Schema::table('invoices',function (Blueprint $table){
            $table->unsignedBigInteger('user_id')->nullable();                                                  //
            $table->foreign('user_id')->references('id')->on('users');                                          // fattura emessa da
        });

        Schema::create('shipment_types', function (Blueprint $table) {                                          // tabella dei tipi di spedizioni per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // tenant
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('act_types', function (Blueprint $table) {                                               // tabella dei tipi di atto per tabella spese postali
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // tenant
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('postal_expenses', function (Blueprint $table) {                                         // tabella delle spese postali
            $table->id();

        // campi comuni
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // tenant
            $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade');                        // cliente
            $table->string('notify_type');                                                                      // enum tipo notifica: NotifyType
            $table->foreignId('new_contract_id')->constrained('new_contracts')->onUpdate('cascade');            // contratto
            $table->string('tax_type');                                                                         // enum tipo entrata: TaxType
            $table->boolean('reinvoice')->default(0);                                                           // bool per rifatturazione spese
            $table->string('order_rif')->nullable();                                                            // riferimento commessa
            $table->string('list_rif')->nullable();                                                             // riferimento distinta

        // campi usati nel caso di notify_type == 'spedizione'
            $table->date('s_shipment_date')->nullable();                                                        // spedito in data
            $table->string('s_month')->nullable();                                                              // enum mese: Month

            $table->unsignedBigInteger('s_shipment_type_id')->nullable();                                       //
            $table->foreign('s_shipment_type_id')->references('id')->on('shipment_types');                      // tipo di spedizione
            // $table->foreignId('s_shipment_type_id')->constrained('shipment_types')->onUpdate('cascade');        // tipo di spedizione

            $table->unsignedBigInteger('s_supplier_id')->nullable();                                            //
            $table->foreign('s_supplier_id')->references('id')->on('suppliers');                                // fornitore
            // $table->foreignId('s_supplier_id')->constrained('suppliers')->onUpdate('cascade');                  // fornitore

            $table->integer('s_year');                                                                          // anno
            $table->string('s_postal_doc_type')->nullable();                                                    // enum tipo documento inviato: PostalDocType
            $table->string('s_product_type')->nullable();                                                       // enum tipo spesa: ProductType
            $table->decimal('s_amount',10,2)->nullable();                                                       // importo

            $table->unsignedBigInteger('s_passive_invoice_id')->nullable();                                     //
            $table->foreign('s_passive_invoice_id')->references('id')->on('passive_invoices');                  // fattura passiva da rifatturare
            // $table->foreignId('s_passive_invoice_id')->constrained('passive_invoices')->onUpdate('cascade');    // fattura passiva da rifatturare

            $table->decimal('s_passive_invoice_expenses',10,2)->nullable();                                     // spese postali
            $table->date('s_passive_invoice_settle_date')->nullable();                                          // saldato in data
            $table->decimal('s_passive_invoice_amount',10,2)->nullable();                                       // totale

        // campi usati nel caso di notify_Type == 'messo'
            $table->date('m_notify_registration_date')->nullable();                                             // notifica registrata in data

            $table->unsignedBigInteger('m_notify_registration_user_id')->nullable();                            //
            $table->foreign('m_notify_registration_user_id')->references('id')->on('users');                    // notifica registrata da utente
            // $table->foreignId('m_notify_registration_user_id')->constrained('users')->onUpdate('cascade');      // notifica registrata da utente

            $table->date('m_scan_import_date')->nullable();                                                     // scansionato e importato in data

            $table->unsignedBigInteger('m_scan_import_user_id')->nullable();                                    //
            $table->foreign('m_scan_import_user_id')->references('id')->on('users');                            // scansionato e importato da utente
            // $table->foreignId('m_scan_import_user_id')->constrained('users')->onUpdate('cascade');           // scansionato e importato da utente

            $table->string('m_send_protocol_number')->nullable();                                               // numero protocollo invio
            $table->date('m_send_protocol_date')->nullable();                                                   // data protocollo invio
            $table->string('m_receive_protocol_number')->nullable();                                            // numero protocollo ricezione
            $table->date('m_receive_protocol_date')->nullable();                                                // data protocollo ricezione
            $table->string('m_supplier')->nullable();                                                           // ente da rimborsare
            $table->unsignedBigInteger('m_act_type_id')->nullable();                                            //
            $table->foreign('m_act_type_id')->references('id')->on('act_types');                                // tipo atto
            $table->string('m_act_id')->nullable();                                                             // id atto
            $table->integer('m_act_year')->nullable();                                                          // anno atto
            $table->string('m_recipient')->nullable();                                                          // destinatario
            $table->decimal('m_amount',10,2)->nullable();                                                       // importo
            $table->string('m_iban')->nullable();                                                               // iban
            $table->string('attachment')->nullable();                                                           // allegato
            $table->boolean('m_payed')->default(0);                                                             // bool pagato
            $table->date('m_payment_date')->nullable();                                                         // pagato in data
            $table->date('m_payment_insert_date')->nullable();                                                  // pagamento inserito in data

            $table->unsignedBigInteger('m_payment_insert_user_id')->nullable();                                 //
            $table->foreign('m_payment_insert_user_id')->references('id')->on('users');                         // inserito pagamento da utente
            // $table->foreignId('m_payment_insert_user_id')->constrained('users')->onUpdate('cascade');           // inserito pagamento da utente

        // altri campi comuni
            // $table->date('reinvoice_insert_date');                                                              // rifatturato in data

            $table->unsignedBigInteger('reinvoice_insert_user_id')->nullable();                                 //
            $table->foreign('reinvoice_insert_user_id')->references('id')->on('users');                         // rifatturato da utente
            // $table->foreignId('reinvoice_insert_user_id')->constrained('users')->onUpdate('cascade');           // 

            $table->foreignId('reinvoice_id')->constrained('invoices')->onUpdate('cascade');                    // fattura
            $table->string('note')->nullable();                                                                 // note




            // $table->foreignId('company_id')->constrained()->onUpdate('cascade');

            // $table->foreignId('new_contract_id')->constrained()->onUpdate('cascade');

            // $table->string('send_number');
            // $table->string('send_date');
            // // $table->string('shipment_type_id');                                                  // in sospeso

            // $table->foreignId('supplier_id')->constrained('suppliers')->onUpdate('cascade');
            // $table->string('recipient');
            // $table->string('tax_type');
            // $table->integer('year');
            // // $table->foreignId('act_type_id')->constrained()->onUpdate('cascade');               // in sospeso
            // $table->string('act_id');
            // $table->integer('act_year');
            // $table->foreignId('shipment_insert_user_id')->constrained('users')->onUpdate('cascade');

            // $table->string('order_rif');
            // $table->string('list_rif');
            // $table->string('receive_number');
            // $table->date('receive_date');
            // $table->date('date');
            // $table->string('month');
            // $table->date('scan_date');
            // $table->date('registration_date');
            // $table->string('attachment');
            // $table->foreignId('notify_insert_user_id')->constrained('users')->onUpdate('cascade');

            // $table->string('expense_type');
            // $table->decimal('expense_amount',10,2)->nullable();
            // $table->decimal('mark_expense_amount',10,2)->nullable();
            // $table->boolean('reinvoice')->default(0);
            // $table->string('iban')->nullable();
            // $table->string('doc_type_id');
            // $table->string('doc_number');
            // $table->date('doc_date');

            // $table->boolean('payed')->default(0);
            // $table->json('payment_dates');
            // $table->date('payment_last_date');
            // $table->decimal('payment_total',10,2)->nullable();
            // $table->foreignId('payment_insert_user_id')->constrained('users')->onUpdate('cascade');
            // $table->date('payment_insert_date');

            // $table->date('send_reinvoice_date');
            // $table->string('reinvoice_number');
            // $table->date('reinvoice_date');
            // $table->decimal('reinvoice_total',10,2)->nullable();
            // $table->foreignId('reinvoice_insert_user_id')->constrained('users')->onUpdate('cascade');
            // $table->date('reinvoice_insert_date');

            // $table->date('notify_date_registration_date');
            // $table->foreignId('notify_date_registration_user_id')->constrained('users')->onUpdate('cascade');

            // $table->string('note');

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
        Schema::dropIfExists('shipment_types');
        Schema::dropIfExists('act_types');
        Schema::enableForeignKeyConstraints();
    }
};
