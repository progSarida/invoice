<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bails', function (Blueprint $table) {
            // Aggiunge tax_types come json
            $table->json('tax_types')->after('cig_code')->nullable();
        });

        // Migra i dati esistenti da tax_type a tax_types
        DB::table('bails')->get()->each(function ($bail) {
            if ($bail->tax_type) {
                DB::table('bails')
                    ->where('id', $bail->id)
                    ->update([
                        'tax_types' => json_encode([$bail->tax_type]),
                    ]);
            }
        });

        Schema::table('bails', function (Blueprint $table) {
            // Rimuove il vecchio campo tax_type
            $table->dropColumn('tax_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bails', function (Blueprint $table) {
            // Ripristina tax_type
            $table->string('tax_type')->after('cig_code')->nullable();
        });

        // Migra i dati indietro da tax_types a tax_type (prende il primo valore se multiplo)
        DB::table('bails')->get()->each(function ($bail) {
            if ($bail->tax_types) {
                $taxTypes = json_decode($bail->tax_types, true);
                $firstTaxType = is_array($taxTypes) && !empty($taxTypes) ? $taxTypes[0] : null;
                DB::table('bails')
                    ->where('id', $bail->id)
                    ->update([
                        'tax_type' => $firstTaxType,
                    ]);
            }
        });

        Schema::table('bails', function (Blueprint $table) {
            $table->dropColumn('tax_types');
        });
    }
};