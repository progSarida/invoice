<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('invoices')
            ->whereNull('flow')
            ->update(['user_id' => 3]);                                                             // fatture vecchie (riccardo)

        DB::table('invoices')
            ->whereNotNull('flow')
            ->update(['user_id' => 4]);                                                             // fatture nuove (daniele)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // DB::table('invoices')                                                                       // ripristinata situazione iniziale
        //     ->update(['user_id' => null]);
    }
};
