<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('secondary_role_id')
                ->nullable()
                ->after('role_id')
                ->constrained('roles')
                ->nullOnDelete();
        });

        $hrManagerRoleId = DB::table('roles')->where('code', 'hr_manager')->value('id');

        if ($hrManagerRoleId) {
            DB::table('users')
                ->whereRaw('LOWER(email) = ?', ['wida.mustika.sari@example.com'])
                ->orWhereRaw('LOWER(email) = ?', ['wida.mus@example.com'])
                ->update(['secondary_role_id' => $hrManagerRoleId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secondary_role_id');
        });
    }
};