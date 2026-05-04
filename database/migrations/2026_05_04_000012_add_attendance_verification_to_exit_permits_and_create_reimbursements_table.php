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
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->foreignId('attendance_checked_by')
                ->nullable()
                ->after('hr_verified_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('attendance_checked_at')
                ->nullable()
                ->after('attendance_checked_by');
            $table->boolean('has_valid_checkin')
                ->nullable()
                ->after('attendance_checked_at');
            $table->enum('post_md_path', ['meal', 'reimbursement'])
                ->nullable()
                ->after('has_valid_checkin');
        });

        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('request_date');
            $table->unsignedInteger('amount')->default(0);
            $table->text('description')->nullable();
            $table->enum('status', [
                'pending_manager',
                'pending_md',
                'pending_ratna',
                'submitted_to_accounting',
                'finished',
                'rejected',
            ])->default('pending_manager');
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('md_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('md_approved_at')->nullable();
            $table->foreignId('ratna_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ratna_submitted_at')->nullable();
            $table->foreignId('accounting_processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accounting_processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reimbursements');

        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_checked_by');
            $table->dropColumn(['attendance_checked_at', 'has_valid_checkin', 'post_md_path']);
        });
    }
};
