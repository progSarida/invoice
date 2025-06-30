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
        Schema::create('limit_motivation_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')            // id azienda per multi-tenancy
                ->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');
            $table->string('description')
                ->nullable();
            $table->timestamps();
        });

        Schema::table('invoices',function (Blueprint $table){
            $table->string('year_limit')->nullable()->after('doc_type_id');                                                 // tipo tempistica: contestuale, differita (Enum)

            // $table->unsignedBigInteger('limit_motivation_type_id')->nullable();
            // $table->foreign('limit_motivation_type_id')->references('id')->on('limit_motivation_types')
            // ->onUpdate('cascade')->onDelete('cascade');
            $table->string('limit_motivation_type_id')->nullable()->after('year_limit');
            // $table->foreignId('limit_motivation_type_id')->constrained('limit_motivation_types')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('limit_motivation_types');
        Schema::enableForeignKeyConstraints();
    }
};
