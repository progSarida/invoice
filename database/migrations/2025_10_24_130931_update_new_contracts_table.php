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
        Schema::table('new_contracts',function (Blueprint $table){
            $table->boolean('reinvoice')->default(0)->after('payment_type');                            // rifatturazione spese postali del contratto
        });

        Schema::table('invoices',function (Blueprint $table){
            $table->unsignedBigInteger('user_id')->nullable();                                          //
            $table->foreign('user_id')->references('id')->on('users');                                  // fattura emessa da
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
