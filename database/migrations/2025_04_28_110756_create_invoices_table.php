<?php

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SdiStatus;
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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');
            $table->foreignId('client_id')->constrained()->onUpdate('cascade');
            // $table->bigInteger('container_id')->nullable();

            $table->unsignedBigInteger('container_id')->nullable();
            $table->foreign('container_id')->references('id')->on('containers');
            // $table->foreignId('container_id')->constrained()->onUpdate('cascade');

            // $table->foreign('client_id')->references('id')->on('clients');
            $table->foreignId('tender_id')->nullable()->constrained();

            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('check_validation')->nullable();

            $table->string('tax_type');
            $table->string('invoice_type');

            $table->integer('number');
            $table->integer('section');
            $table->integer('year');

            $table->date('invoice_date');

            $table->year('budget_year');//anno di bilancio
            $table->string('accrual_type');//tipo di competenza
            $table->year('accrual_year');//anno di competenza

            $table->text('description');
            $table->text('free_description');

            $table->decimal('vat_percentage');
            $table->decimal('vat');

            $table->boolean('is_total_with_vat')->default(1);

            $table->decimal('importo',10,2)->nullable();
            $table->decimal('spese',10,2)->nullable();
            $table->decimal('rimborsi',10,2)->nullable();
            $table->decimal('ordinario',10,2)->nullable();
            $table->decimal('temporaneo',10,2)->nullable();
            $table->decimal('affissioni',10,2)->nullable();
            $table->decimal('bollo',10,2)->nullable();

            $table->decimal('total',10,2);
            $table->decimal('no_vat_total',10,2)->nullable();

            $table->foreignId('bank_account_id')->nullable()->constrained();
            $table->string('payment_status')->default(PaymentStatus::WAITING);
            $table->string('payment_type')->nullable();
            $table->integer('payment_days')->default(0);

            $table->decimal('total_payment',10,2)->nullable();
            $table->date('last_payment_date')->nullable();

            $table->string('sdi_code')->nullable();
            $table->string('sdi_status')->default(SdiStatus::DA_INVIARE);
            $table->date('sdi_date')->nullable();

            $table->string('pdf_path')->nullable();
            $table->string('xml_path')->nullable();

            $table->timestamps();
        });

        Schema::table('invoices',function (Blueprint $table){
            $table->foreign('parent_id')->references('id')->on('invoices')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('sdi_notifications', function (Blueprint $table) {

            $table->id();
            $table->foreignId('invoice_id')->constrained()->onUpdate('cascade')->onDelete('cascade');

            $table->string('code')->nullable();                                     // codice sdi
            $table->string('status')->nullable();                                   // stato sdi (Enum)
            $table->date('date')->nullable();                                       // data
            $table->string('description')->nullable();                              // descrizione

            $table->timestamps();

        });

        Schema::create('invoice_items', function (Blueprint $table) {

            $table->id();
            $table->foreignId('invoice_id')->constrained()->onUpdate('cascade')->onDelete('cascade');

            $table->string('description');
            $table->decimal('amount',10,2);
            $table->boolean('is_with_vat')->default(1);

            $table->timestamps();

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sdi_notifications');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::enableForeignKeyConstraints();
    }
};
