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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade');
            $table->foreignId('client_id')->constrained()->onUpdate('cascade');

            $table->integer('tender_id')->nullable();
            
            $table->string('name');
            $table->string('tax_types');
            $table->string('accrual_types');

            $table->timestamps();
        });


// SELECT INV.description, INV.tax_type, INV.tender_id, T.office_name, T.office_code, T.cig_code,
// C.id, C.client_id, C.number, C.contract_date 
// FROM `contracts` as C 
// JOIN invoices as INV ON 

// INV.client_id=C.client_id 
// AND INV.description LIKE CONCAT('%',  C.number,' del ', DATE_FORMAT(C.contract_date, "%d/%m/%Y"), '%')
// AND INV.tax_type=C.tax_type

// JOIN tenders as T ON T.id=INV.tender_id
// WHERE C.number != "" AND C.contract_date is not null
// GROUP BY C.id  
// ORDER BY `C`.`number` ASC;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('containers');
        Schema::enableForeignKeyConstraints();
    }
};
