<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aggiunge il flag che distingue le fatture passive scaricate dallo SdI da quelle
     * inserite a mano: solo le seconde potranno essere eliminate.
     *
     * Il flag è false di default (inserimento manuale) e viene messo a true dallo
     * scarico SdI. Le fatture già presenti vengono marcate in base a "filename",
     * valorizzato unicamente dall'import (AndxorSoapService::createPassiveInvoice)
     * e non modificabile dal form.
     */
    public function up(): void
    {
        Schema::table('passive_invoices', function (Blueprint $table) {
            $table->boolean('downloaded')->default(false)->after('parent_id');   // true = fattura scaricata dallo SdI
        });

        $downloaded = DB::table('passive_invoices')
            ->whereNotNull('filename')
            ->update(['downloaded' => true]);

        echo "✅  Marcate come scaricate da SdI {$downloaded} fatture passive\n";
    }

    public function down(): void
    {
        Schema::table('passive_invoices', function (Blueprint $table) {
            $table->dropColumn('downloaded');
        });
    }
};
