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
            $table->json('social_contributions')->nullable()->after('art_73');
            $table->json('withholdings')->nullable()->after('social_contributions');
            $table->string('vat_enforce_type')->nullable()->after('withholdings');
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
