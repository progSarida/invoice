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
        Schema::table('new_contracts', function (Blueprint $table) {
            $table->json('tax_types')->after('client_id')->nullable();                                  // aggiungo il campo tax_types come json e rimuovo tax_type
            $table->dropColumn('tax_type');

            $table->json('accrual_types')->after('end_validity_date')->nullable();                      // aggiungo il campo accrual_types come json e rimuovo accrual_type_id
            $table->dropForeign(['accrual_type_id']);
            $table->dropColumn('accrual_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_contracts', function (Blueprint $table) {
            $table->string('tax_type')->after('client_id')->nullable();                                 // ripristino tax_type
            $table->dropColumn('tax_types');

            $table->unsignedBigInteger('accrual_type_id')->after('end_validity_date')->nullable();      // ripristino accrual_type_id
            $table->foreign('accrual_type_id')->references('id')->on('accrual_types')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->dropColumn('accrual_types');
        });
    }
};

