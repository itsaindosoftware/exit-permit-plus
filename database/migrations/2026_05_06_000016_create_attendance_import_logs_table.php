<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_permit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_disk', 60)->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_file_name')->nullable();
            $table->string('import_type', 40)->default('preview');
            $table->timestamp('imported_at')->nullable();
            $table->unsignedInteger('total_requestors')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->boolean('has_valid_checkin')->default(false);
            $table->timestamps();

            $table->index(['import_type', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_import_logs');
    }
};
