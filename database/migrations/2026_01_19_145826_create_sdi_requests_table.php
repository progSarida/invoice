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
        Schema::create('sdi_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                 // tenant
            $table->date('request_date')->nullable();                                                       // data richiesta
            $table->string('sdi_request_type')->nullable();                                                 // tipo richiesta (enum SdiRequestType)
            $table->unsignedBigInteger('invoice_id')->nullable();                                           // id fattura se richiesta singola
            $table->foreign('invoice_id')->references('id')->on('invoices')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sdi_requests');
    }
};
