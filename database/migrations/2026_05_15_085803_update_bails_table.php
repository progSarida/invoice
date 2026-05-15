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
        Schema::table('bails', function (Blueprint $table) {
            $table->decimal('first_premium', 10, 2)->default(0.00)->after('day_duration');                                              // importo premio iniziale
            $table->decimal('renewal_premium', 10, 2)->default(0.00)->after('first_premium');                                           // importo premio per rinnovo
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
