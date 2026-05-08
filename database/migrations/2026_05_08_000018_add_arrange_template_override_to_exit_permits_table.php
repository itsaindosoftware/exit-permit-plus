<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->json('arrange_template_override')->nullable()->after('driver_name');
        });
    }

    public function down(): void
    {
        Schema::table('exit_permits', function (Blueprint $table) {
            $table->dropColumn('arrange_template_override');
        });
    }
};
