<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HrManagerUserSeeder extends Seeder
{
    /**
     * Seed a default HR Manager account.
     */
    public function run(): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'hr_manager'],
            ['name' => 'HR Manager'],
        );

        User::query()->updateOrCreate(
            ['email' => 'hr.manager@example.com'],
            [
                'name' => 'HR Manager',
                'password' => Hash::make('password'),
                'role_id' => $role->id,
                'is_available_for_approval' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
