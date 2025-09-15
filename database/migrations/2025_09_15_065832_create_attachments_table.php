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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');     // tenant
            $table->unsignedBigInteger('client_id')->nullable();                                // cliente
            $table->foreign('client_id')->references('id')->on('clients')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('contract_id')->nullable();                              // contratto
            $table->foreign('contract_id')->references('id')->on('new_contracts')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('element_table');                                                    // tabella originale allegato
            $table->unsignedBigInteger('element_id');                                           // id dell'elemento nella tabella specificata
            $table->string('attachment_type')->nullable();                                      // tipo di allegato
            $table->string('attachment_filename')->nullable();                                  // nome file
            $table->date('attachment_date')->nullable();                                        // data allegato
            $table->date('attachment_upload_date')->nullable();                                 // data caricamento allegato
            $table->string('attachment_path')->nullable();                                      // percorso file allegato
            $table->timestamps();
        });

        Schema::table('bails', function (Blueprint $table) {
            $table->date('receipt_date')->after('receipt_attachment_path')->nullable();
        });

        Schema::table('contract_deatils', function (Blueprint $table) {
            $table->string('contract_attachment_path')->nullable();                             // percorso file contratto
        });

        Schema::table('postal_expenses', function (Blueprint $table) {
            $table->date('act_date')->after('act_attachment_path')->nullable();                 // data atto notificato
            $table->date('notify_date')->after('notify_attachment_path')->nullable();           // data documento notifica
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
