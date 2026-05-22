<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->unsignedInteger('meal_unit_price')->default(12000);
            $table->decimal('local_tax_rate', 5, 2)->default(10);
            $table->decimal('service_tax_rate', 5, 2)->default(2);
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_suppliers');
    }
};