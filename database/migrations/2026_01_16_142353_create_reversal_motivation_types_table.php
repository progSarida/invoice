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
        Schema::create('reversal_motivation_types', function (Blueprint $table) {
            $table->id();
            $table->string('reversal_group_type');
            $table->integer('order');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('reversal_group_type')->after('parent_id')->nullable();                                  // gruppo annullamento
            $table->unsignedBigInteger('reversal_motivation_type_id')->after('reversal_group_type')->nullable();    // id motivazione emissione nota di credito
            $table->foreign('reversal_motivation_type_id')->references('id')->on('reversal_motivation_types')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reversal_motivation_types');
    }
};
