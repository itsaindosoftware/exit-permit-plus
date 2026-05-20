<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->boolean('plan_check_in')->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropColumn('plan_check_in');
        });
    }
};
