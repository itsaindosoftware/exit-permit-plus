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
        Schema::create('exit_permit_requestors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_permit_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('name', 120);
            $table->string('employee_id', 60)->nullable();
            $table->string('position', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('reimburs_lunch_box', 10)->nullable();
            $table->timestamps();

            $table->index(['exit_permit_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_permit_requestors');
    }
};
