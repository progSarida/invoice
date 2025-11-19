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
        Schema::create('doc_type_sectional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sectional_id')->constrained('sectionals')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('doc_type_id')->constrained('doc_types')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('doc_type_sectional');
        Schema::enableForeignKeyConstraints();
    }
};
