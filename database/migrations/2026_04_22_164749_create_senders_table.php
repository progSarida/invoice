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
        Schema::create('senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade')->onDelete('cascade');           // id tenant
            $table->string('public_name', 100);                                                                 // nome pubblico
            $table->string('address', 100);                                                                     // indirizzo email
            $table->string('connection_safety_type', 30);                               // tipo connessione
            $table->string('out_mail_server', 100);                                                             // server uscita
            $table->string('out_mail_protocol_type', 10);                                                       // protocollo uscita
            $table->string('out_mail_port', 10);                                                                // porta uscita
            $table->boolean('out_authentication');                                                              // richiesta autenticazione
            $table->string('out_username', 100);                                                                // username uscita
            $table->string('out_password', 250);                                                                // password uscita
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('senders');
    }
};
