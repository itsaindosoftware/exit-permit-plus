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
            $table->string('exit_type')->default('sick')->after('destination');
            $table->string('vehicle_plate')->nullable()->after('exit_type');
            $table->boolean('returned_to_office')->default(false)->after('vehicle_plate');
            $table->boolean('eligible_for_meal')->default(false)->after('returned_to_office');
            $table->unsignedInteger('reimbursement_amount')->default(12000)->after('eligible_for_meal');
        });

        Schema::table('order_meals', function (Blueprint $table) {
            $table->unsignedInteger('actual_quantity')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_meals', function (Blueprint $table) {
            $table->dropColumn('actual_quantity');
        });

        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropColumn([
                'exit_type',
                'vehicle_plate',
                'returned_to_office',
                'eligible_for_meal',
                'reimbursement_amount',
            ]);
        });
    }
};