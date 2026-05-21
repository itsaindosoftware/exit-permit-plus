<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CarSeeder::class,
            DriverSeeder::class,
        ]);

        Role::upsert([
            ['code' => 'user', 'name' => 'User'],
            ['code' => 'hr', 'name' => 'HR'],
            ['code' => 'hr_manager', 'name' => 'HR Manager'],
            ['code' => 'manager', 'name' => 'Manager'],
            ['code' => 'md', 'name' => 'Managing Director'],
            ['code' => 'accounting', 'name' => 'Accounting'],
            ['code' => 'admin', 'name' => 'Administrator'],
        ], ['code'], ['name']);

        $this->call([
            HrManagerUserSeeder::class,
        ]);

        // User::factory(10)->create();

        $rolesByCode = Role::query()
            ->whereIn('code', ['user', 'hr', 'hr_manager', 'manager', 'md', 'accounting', 'admin', 'production'])
            ->pluck('id', 'code');

        $defaultUsers = [
            [
                'name' => 'Operational User',
                'email' => 'user@example.com',
                'role_code' => 'user',
            ],
            [
                'name' => 'HR Officer',
                'email' => 'hr@example.com',
                'role_code' => 'hr',
            ],
            [
                'name' => 'Plant Manager',
                'email' => 'manager@example.com',
                'role_code' => 'manager',
            ],
            [
                'name' => 'Managing Director',
                'email' => 'md@example.com',
                'role_code' => 'md',
            ],
            [
                'name' => 'System Administrator',
                'email' => 'admin@example.com',
                'role_code' => 'admin',
            ],
            [
                'name' => 'Accounting Officer',
                'email' => 'accounting@example.com',
                'role_code' => 'accounting',
            ],
            [
                'name' => 'Wida Mustika Sari',
                'email' => 'wida.mustika.sari@example.com',
                'role_code' => 'manager',
            ],
            [
                'name' => 'Ratna',
                'email' => 'hrga-01@thaisummit.co.id',
                'role_code' => 'hr',
            ],
            [
                'name' => 'Theresia Saing',
                'email' => 'theresia.saing@example.com',
                'role_code' => 'hr',
            ],
            [
                'name' => 'Sisca Dewiyani',
                'email' => 'payroll.hr@thaisummit.co.id',
                'role_code' => 'hr',
            ],
            // buat 1 akun admin setiap departemen untuk sebagi usernya
            [
                'name' => 'Indri',
                'email' => 'indri@example.com',
                'role_code' => 'production',
            ],
            [
                'name' => 'Rydha',
                'email' => 'rydha@example.com',
                'role_code' => 'mtn_dies',
            ],
            [
                'name' => 'Sakti',
                'email' => 'sakti@example.com',
                'role_code' => 'ppic',
            ],

        ];

        foreach ($defaultUsers as $defaultUser) {
            User::query()->updateOrCreate(
                ['email' => $defaultUser['email']],
                [
                    'name' => $defaultUser['name'],
                    'password' => Hash::make('password'),
                    'role_id' => $rolesByCode[$defaultUser['role_code']] ?? null,
                    'is_available_for_approval' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
