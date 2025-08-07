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
            $table->unsignedBigInteger('contract_detail_id')->nullable()->after('contract_id');
            $table->foreign('contract_detail_id')->references('id')->on('contract_details')
            ->onUpdate('cascade')->onDelete('cascade');
            // $table->string('contract_detail_id')->nullable()->after('contract_id');
            // $table->foreignId('contract_detail_id')->constrained('limit_motivation_types')->onUpdate('cascade')->onDelete('cascade');

            $table->boolean('art_73')->default(0)->after('invoice_type');
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
