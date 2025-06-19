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
        Schema::create('invoice_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');
            $table->string('name');                                 // codice
            $table->string('description')                           // descrizione testuale
                ->nullable();
            $table->decimal('amount');
            $table->string('vat_code_type');                        // Enum
            $table->timestamps();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('invoice_element_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('invoice_elements')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->decimal('total')->after('amount');
            $table->string('vat_code_type')->after('total');                        // Enum
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('invoice_elements');
        Schema::enableForeignKeyConstraints();
    }
};
