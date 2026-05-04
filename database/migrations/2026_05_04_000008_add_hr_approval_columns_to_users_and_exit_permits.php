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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_available_for_approval')
                ->default(true)
                ->after('role_id');
        });

        Schema::table('exit_permits', function (Blueprint $table) {
            $table->foreignId('hr_approver_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hr_approver_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_available_for_approval');
        });
    }
};
