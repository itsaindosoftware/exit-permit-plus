<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->string('cost_center_sap', 60)->nullable()->after('name');
            $table->string('desc_cost_c', 255)->nullable()->after('cost_center_sap');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropColumn(['cost_center_sap', 'desc_cost_c']);
        });
    }
};
