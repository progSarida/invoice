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
            $table->string('flow')->nullable()->after('id');                                                 // tipo fattura: in => passiva, out => attiva (Enum)

            $table->unsignedBigInteger('accrual_type_id')->nullable()->after('accrual_year');
            $table->foreign('accrual_type_id')->references('id')->on('accrual_types')->after('accrual_year')
            ->onUpdate('cascade')->onDelete('cascade');
            // $table->string('accrual_type_id')->nullable();                                      // id tipo di competenza
            // $table->foreignId('accrual_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedBigInteger('manage_type_id')->nullable()->after('accrual_type_id');
            $table->foreign('manage_type_id')->references('id')->on('manage_types')->after('accrual_type_id')
            ->onUpdate('cascade')->onDelete('cascade');
            // $table->string('manage_type_id')->nullable();                           // id tipo di gestione
            // $table->foreignId('manage_type_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
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
