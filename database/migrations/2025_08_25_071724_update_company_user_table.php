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
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);                                                                       // rimuovo il vincolo di chiave esterna esistente
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');    // aggiungo il nuovo vincolo con ON DELETE CASCADE
            $table->boolean('is_manager')->default(false)->after('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('is_manager');
        });
    }
};
