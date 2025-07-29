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
        Schema::create('passive_downloads', function (Blueprint $table) {           // tabella scarichi fatture passive
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');    // id tenant
            $table->date('date');                                                   // data ricerca
            $table->dateTime('start_date');                                         // data inizio ricerca
            $table->dateTime('end_date');                                           // data fine ricerca
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
