<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('passive_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('iban');                               // utente che ha registrato la fattura passiva
            $table->foreign('user_id')->references('id')->on('users')
                ->onUpdate('cascade');

            $table->date('pi_validation_date')->nullable()->after('pi_validation_id');                      // data validazione fattura passiva
            $table->unsignedBigInteger('pi_validation_user_id')->nullable()->after('pi_validation_date');   // utente che ha validato la fattura passiva
            $table->foreign('pi_validation_user_id')->references('id')->on('users')
                ->onUpdate('cascade');
        });

        DB::table('passive_invoices')->update([                                                             // popolamento record esistenti
            'user_id' => 3,
        ]);

        DB::table('passive_invoices')                                                                       // solo le fatture già validate
            ->whereNotNull('pi_validation_id')
            ->update([
                'pi_validation_date' => DB::raw('updated_at'),
                'pi_validation_user_id' => 3,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passive_invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['pi_validation_user_id']);
            $table->dropColumn(['user_id', 'pi_validation_date', 'pi_validation_user_id']);
        });
    }
};
