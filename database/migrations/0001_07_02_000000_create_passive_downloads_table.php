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
        Schema::create('passive_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');                // id tenant
            $table->date('date');                                                               // data ultime fatture scaricate
            $table->integer('new_suppliers');                                                   // nuovi fornitori inseriti
            $table->integer('new_invoices');                                                    // nuove fatture passive scaricate
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('passive_downloads');
        Schema::enableForeignKeyConstraints();
    }
};
