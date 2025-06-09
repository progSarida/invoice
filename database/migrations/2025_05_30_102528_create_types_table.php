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
        Schema::create('accrual_types', function (Blueprint $table) {
            $table->id();
            $table->integer('order');
            $table->string('name');
            $table->string('ref');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('manage_types', function (Blueprint $table) {
            $table->id();
            $table->integer('order');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('accrual_types');
        Schema::dropIfExists('manage_types');
        Schema::enableForeignKeyConstraints();
    }
};
