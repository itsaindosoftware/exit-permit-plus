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
            $table->boolean('order_car')->default(false)->after('exit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropColumn('order_car');
        });
    }
};
