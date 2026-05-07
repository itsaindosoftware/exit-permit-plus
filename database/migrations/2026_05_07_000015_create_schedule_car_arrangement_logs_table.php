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
        Schema::create('schedule_car_arrangement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_permit_id')->constrained('exit_permits')->cascadeOnDelete();
            $table->foreignId('arranged_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('arranged_at');
            $table->string('action', 20);
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('vehicle_plate')->nullable();
            $table->string('driver_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_car_arrangement_logs');
    }
};
