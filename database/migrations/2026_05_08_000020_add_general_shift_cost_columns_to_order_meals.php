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
            $table->unsignedInteger('day_shift_qty')->default(0)->after('schedule_type');
            $table->unsignedInteger('overtime_day_shift_qty')->default(0)->after('day_shift_qty');
            $table->unsignedInteger('night_shift_qty')->default(0)->after('overtime_day_shift_qty');
            $table->unsignedInteger('overtime_night_shift_qty')->default(0)->after('night_shift_qty');
            $table->unsignedInteger('meal_unit_price')->default(12000)->after('overtime_night_shift_qty');
            $table->decimal('local_tax_rate', 5, 2)->default(10)->after('meal_unit_price');
            $table->decimal('service_tax_rate', 5, 2)->default(2)->after('local_tax_rate');
            $table->unsignedBigInteger('subtotal_amount')->default(0)->after('service_tax_rate');
            $table->unsignedBigInteger('local_tax_amount')->default(0)->after('subtotal_amount');
            $table->unsignedBigInteger('service_tax_amount')->default(0)->after('local_tax_amount');
            $table->unsignedBigInteger('total_amount')->default(0)->after('service_tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_meals', function (Blueprint $table) {
            $table->dropColumn([
                'day_shift_qty',
                'overtime_day_shift_qty',
                'night_shift_qty',
                'overtime_night_shift_qty',
                'meal_unit_price',
                'local_tax_rate',
                'service_tax_rate',
                'subtotal_amount',
                'local_tax_amount',
                'service_tax_amount',
                'total_amount',
            ]);
        });
    }
};
