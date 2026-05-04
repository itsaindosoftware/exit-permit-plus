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
        Schema::create('exit_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('permit_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('destination');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('md_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('md_approved_at')->nullable();
            $table->foreignId('hr_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_permits');
    }
};
