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
        Schema::table('company_docs', function (Blueprint $table) {
            $table->unsignedInteger('order')                     // posizione del documento nelle select dell'azienda
                ->default(9999)
                ->after('doc_type_id');
        });

        DB::table('company_docs')                                                       // ordinamento iniziale per codice documento
            ->join('doc_types', 'doc_types.id', '=', 'company_docs.doc_type_id')
            ->orderBy('company_docs.company_id')
            ->orderBy('doc_types.name')
            ->select('company_docs.id', 'company_docs.company_id')
            ->get()
            ->groupBy('company_id')
            ->each(function ($rows) {
                $position = 1;
                foreach ($rows as $row) {
                    DB::table('company_docs')->where('id', $row->id)->update(['order' => $position++]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_docs', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
