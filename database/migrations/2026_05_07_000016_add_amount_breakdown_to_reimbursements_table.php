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
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->unsignedInteger('amount_order_meal')->default(0)->after('amount');
            $table->unsignedInteger('amount_fuel')->default(0)->after('amount_order_meal');
            $table->unsignedInteger('amount_toll')->default(0)->after('amount_fuel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn(['amount_order_meal', 'amount_fuel', 'amount_toll']);
        });
    }
};
