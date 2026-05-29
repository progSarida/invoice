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
        Schema::create('model_types', function (Blueprint $table) {                                                         // tabella dei tipi di modelli
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                                 // tenant
            $table->integer('order');                                                                                       // posizione
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('model_sub_types', function (Blueprint $table) {                                                     // tabella dei sottotipi di modelli
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade');                                 // tenant
            $table->foreignId('model_type_id')->constrained('model_types')->onUpdate('cascade');                            // tipo modello
            $table->integer('order');                                                                                       // posizione
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');                                            // id azienda per multi-tenancy
            $table->foreignId('model_type_id')->constrained('model_types')->onUpdate('cascade');                            // tipo modello
            $table->foreignId('model_subtype_id')->constrained('model_sub_types')->onUpdate('cascade');                     // sottotipo modello
            $table->string('filename');                                                                                     // nome file modello
            $table->string('description');                                                                                  // descrizione modello
            $table->date('upload_date');                                                                                    // data caricamento modello
            $table->string('path');                                                                                         // percorso file modello
            $table->boolean('current')->default(false);                                                                     // flag modello in vigore
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
        Schema::dropIfExists('model_sub_types');
        Schema::dropIfExists('model_types');
    }
};
