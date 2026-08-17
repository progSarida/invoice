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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()                                          // id tenant
                ->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('passive_payment_tolerance', 8, 2)->default(0);                         // tolleranza sul residuo oltre la quale una fattura passiva è considerata non pagata
            $table->timestamps();
        });

        // Creo la riga dei parametri per le aziende già esistenti, con i valori di default
        $now = now();
        $companies = DB::table('companies')->pluck('id');
        foreach ($companies as $companyId) {
            DB::table('company_settings')->insert([
                'company_id' => $companyId,
                'passive_payment_tolerance' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
