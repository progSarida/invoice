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
        Schema::table('bail_deatils', function (Blueprint $table) {
            $table->date('release_date')->nullable();                                                       // data svincolo
            $table->string('release_path')->nullable();                                                     // percorso allegato svincolo
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
