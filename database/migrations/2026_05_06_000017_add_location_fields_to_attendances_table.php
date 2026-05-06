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
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_in_ip');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->string('check_in_street_area', 255)->nullable()->after('check_in_longitude');
            $table->string('check_in_village', 120)->nullable()->after('check_in_street_area');
            $table->string('check_in_district', 120)->nullable()->after('check_in_village');
            $table->string('check_in_regency', 120)->nullable()->after('check_in_district');

            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_ip');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->string('check_out_street_area', 255)->nullable()->after('check_out_longitude');
            $table->string('check_out_village', 120)->nullable()->after('check_out_street_area');
            $table->string('check_out_district', 120)->nullable()->after('check_out_village');
            $table->string('check_out_regency', 120)->nullable()->after('check_out_district');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_latitude',
                'check_in_longitude',
                'check_in_street_area',
                'check_in_village',
                'check_in_district',
                'check_in_regency',
                'check_out_latitude',
                'check_out_longitude',
                'check_out_street_area',
                'check_out_village',
                'check_out_district',
                'check_out_regency',
            ]);
        });
    }
};
