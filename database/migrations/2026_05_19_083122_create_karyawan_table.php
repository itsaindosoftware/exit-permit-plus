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
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();

            $table->integer('no');
            $table->string('id_employee', 50)->unique();
            $table->string('name', 150);

            $table->string('old_level', 20)->nullable();
            $table->string('new_level', 20)->nullable();

            $table->string('department', 150)->nullable();
            $table->string('section', 150)->nullable();

            $table->bigInteger('cost_center_sap')->nullable();

            $table->string('desc_cost_c', 150)->nullable();

            $table->string('type', 20)->nullable();
            $table->string('factory_office', 50)->nullable();

            $table->date('hire_date')->nullable();

            $table->decimal('years_of_service', 5, 1)->nullable();

            $table->string('status_employee', 20)->nullable();
            $table->string('position', 100)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawans');
    }
};