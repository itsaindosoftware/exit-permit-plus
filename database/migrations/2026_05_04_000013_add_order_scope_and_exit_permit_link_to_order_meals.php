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
        Schema::table('order_meals', function (Blueprint $table) {
            $table->enum('order_scope', ['general', 'exit_permit'])
                ->default('general')
                ->after('user_id');
            $table->foreignId('exit_permit_id')
                ->nullable()
                ->after('order_scope')
                ->constrained('exit_permits')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_meals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exit_permit_id');
            $table->dropColumn('order_scope');
        });
    }
};
