<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropForeign(['exit_permit_id']);
        });

        DB::statement('ALTER TABLE reimbursements MODIFY exit_permit_id BIGINT UNSIGNED NULL');

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->foreign('exit_permit_id')
                ->references('id')
                ->on('exit_permits')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('reimbursements')
            ->whereNull('exit_permit_id')
            ->delete();

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropForeign(['exit_permit_id']);
        });

        DB::statement('ALTER TABLE reimbursements MODIFY exit_permit_id BIGINT UNSIGNED NOT NULL');

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->foreign('exit_permit_id')
                ->references('id')
                ->on('exit_permits')
                ->cascadeOnDelete();
        });
    }
};
