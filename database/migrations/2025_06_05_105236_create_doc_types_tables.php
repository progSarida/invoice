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
        Schema::create('doc_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')
                ->nullable();
            $table->timestamps();
        });

        Schema::create('doc_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_group_id')                       // id gruppo documento
                ->constrained('doc_groups')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('name');                                 // codice
            $table->string('description')                           // descrizione testuale
                ->nullable();
            $table->timestamps();
        });

        Schema::create('company_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')                         // id azienda
                ->constrained('companies')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('doc_type_id')                        // id tipo documento
                ->constrained('doc_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_docs');
        Schema::dropIfExists('doc_types');
        Schema::dropIfExists('doc_groups');
        Schema::enableForeignKeyConstraints();
    }
};
