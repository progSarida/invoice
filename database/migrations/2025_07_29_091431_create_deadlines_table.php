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
        Schema::create('deadlines', function (Blueprint $table) {                   // tabella scadenze
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');    // id tenant
            $table->string('description');                                          // descrizione scadenza
            $table->string('note');                                                 // note scadenza
            $table->date('date');                                                   // data scadenza
            $table->boolean('dispatched')->default(0);                              // flag evasione scadenza
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('deadlines');
        Schema::enableForeignKeyConstraints();
    }
};
