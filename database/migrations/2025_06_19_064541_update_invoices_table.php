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
        Schema::table('invoices',function (Blueprint $table){
            $table->string('invoice_type')->nullable()->change();

            // $table->unsignedBigInteger('doc_type_id')->nullable();
            // $table->foreign('doc_type_id')->references('id')->on('doc_types')
            // ->onUpdate('cascade')->onDelete('cascade');
            $table->string('doc_type_id')->nullable()->after('invoice_type');
            // $table->foreignId('doc_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->string('section')->nullable()->change();

            // $table->unsignedBigInteger('sectional_id')->nullable();
            // $table->foreign('sectional_id')->references('id')->on('sectionals')
            // ->onUpdate('cascade')->onDelete('cascade');
            $table->string('sectional_id')->nullable()->after('section');
            // $table->foreignId('sectional_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
