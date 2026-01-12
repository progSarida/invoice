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
            $table->string('bail_type')->after('agency_id')->nullable();                                    // tipo (Enum BailType)
            $table->string('condition_attachment_path')->after('bill_attachment_path')->nullable();         // percorso allegato condizioni
        });

        Schema::create('bail_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bail_id')->constrained('bails')->onUpdate('cascade');                        // polizza
            $table->date('bill_start')->nullable();                                                         // data inizio polizza
            $table->date('bill_deadline')->nullable();                                                      // data fine polizza
            $table->decimal('premium',10,2)->nullable();                                                    // premio
            $table->string('bail_status')->nullable();                                                      // stato (Enum BailStatus)
            $table->date('pay_date')->nullable();                                                           // data pagamento premio
            $table->date('receipt_date')->nullable();                                                       // data polizza
            $table->string('attachment_path')->nullable();                                                  // percorso allegato quietanza
            $table->date('release_date')->nullable();                                                       // data svincolo
            $table->string('release_path')->nullable();                                                     // percorso allegato svincolo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bail_details');
    }
};
