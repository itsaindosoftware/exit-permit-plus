<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('order_car')
                ->constrained('cost_centers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });
    }
};
