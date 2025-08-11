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
        Schema::create('shipment_types', function (Blueprint $table) {                                          // tabella dei tipi di spedizioni per tabella spese postali
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    
        Schema::create('postal_expenses', function (Blueprint $table) {                                         // tabella delle spese postali
            $table->id();

            // campi comuni
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                     // 
            $table->foreignId('client_id')->constrained('clients')->onUpdate('cascade');                        // 
            $table->string('notify_type');                                                                      // Enum NotifyType: 'spedizione', 'messo'
            $table->foreignId('new_contract_id')->constrained('new_contracts')->onUpdate('cascade');            // 
            $table->string('s_order_rif');                                                                      // 
            $table->string('s_list_rif');                                                                       // 

            // campi usati nel caso di notify_type == 'spedizione'
            $table->date('s_shipment_date');                                                                    // 
            $table->string('s_month');                                                                          // Enum Month
            $table->foreignId('s_shipment_type_id')->constrained('shipment_types')->onUpdate('cascade');        // Tipo di spedizione
            $table->foreignId('s_supplier_id')->constrained('suppliers')->onUpdate('cascade');                  // 
            $table->foreignId('s_new_contract_id')->constrained('new_contracts')->onUpdate('cascade');          // 
            $table->string('s_postal_doc_type');                                                                // Enum PostalDocType
            $table->integer('s_year');                                                                          // 
            $table->string('s_product_type');                                                                   // Enum ProductType
            $table->decimal('s_amount',10,2)->nullable();                                                       // 
            $table->foreignId('s_passive_invoice_id')->constrained('passive_invoices')->onUpdate('cascade');    // 
            $table->decimal('s_passive_invoice_expenses',10,2)->nullable();                                     // 
            $table->date('s_passive_invoice_settle_date');                                                      // 
            $table->decimal('s_passive_invoice_amount',10,2)->nullable();                                       // 

            // campi usati nel caso di notify_Type == 'messo'
            $table->date('m_notify_registration_date');                                                         // 
            $table->foreignId('m_notify_registration_user_id')->constrained('users')->onUpdate('cascade');      // 
            $table->date('m_scan_import_date');                                                                 // 
            $table->foreignId('m_scan_import_user_id')->constrained('users')->onUpdate('cascade');              // 
            $table->string('m_send_protocol_number');                                                           // 
            $table->date('m_send_protocol_date');                                                               // 
            $table->string('m_receive_protocol_number');                                                        // 
            $table->date('m_receive_protocol_date');                                                            // 
            // $table->foreignId('m_supplier_id')->constrained('suppliers')->onUpdate('cascade');                  // 
            $table->string('m_supplier');                                                                       // 
            $table->string('m_tax_type');                                                                       // 
            $table->string('m_product_type');                                                                   // Enum ProductType
            $table->string('m_product_id');                                                                     // 
            $table->integer('m_product_year');                                                                  // 
            $table->string('m_recipient');                                                                      // 
            $table->decimal('m_amount',10,2)->nullable();                                                       // 
            $table->boolean('m_art_15')->default(0);                                                            // 
            $table->string('m_iban');                                                                           // 
            $table->string('attachment');                                                                       // 
            $table->boolean('m_payed')->default(0);                                                             // 
            $table->date('m_payment_date');                                                                     // 
            $table->date('m_payment_insert_date');                                                              // 
            $table->foreignId('m_payment_insert_user_id')->constrained('users')->onUpdate('cascade');           // 

            // 
            $table->date('reinvoice_insert_date');                                                              // 
            $table->foreignId('reinvoice_insert_user_id')->constrained('users')->onUpdate('cascade');           // 
            $table->foreignId('reinvoice_id')->constrained('invoices')->onUpdate('cascade');                    // 
            $table->string('note')->nullable();                                                                 // 
            



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
        Schema::enableForeignKeyConstraints();
    }
};
