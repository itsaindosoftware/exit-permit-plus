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
            $table->unsignedInteger('visitor_count')->default(0)->after('actual_quantity');
            $table->string('schedule_type')->default('single')->after('visitor_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_meals', function (Blueprint $table) {
            $table->dropColumn(['visitor_count', 'schedule_type']);
        });
    }
};
