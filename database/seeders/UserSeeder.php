<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

//a
class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'id' => 6,
                'name' => 'HR Manager',
                'nik' => '6',
                'email' => 'hr.manager@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:25',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 8,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => null,
                'created_at' => '2026-05-07 03:14:25',
                'updated_at' => '2026-05-13 11:47:34',
            ],
            [
                'id' => 7,
                'name' => 'Operational User',
                'nik' => '7',
                'email' => 'user@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:25',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 6,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => 'a9489f59076197951a79d25fa492f607992e5842faebe1566795f899ed8d94b6',
                'created_at' => '2026-05-07 03:14:25',
                'updated_at' => '2026-05-13 11:47:34',
            ],
            [
                'id' => 8,
                'name' => 'HR Officer',
                'nik' => '8',
                'email' => 'hr@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:25',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 7,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => null,
                'created_at' => '2026-05-07 03:14:25',
                'updated_at' => '2026-05-13 11:47:34',
            ],
            [
                'id' => 9,
                'name' => 'Plant Manager',
                'nik' => '9',
                'email' => 'manager@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:26',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 9,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => null,
                'created_at' => '2026-05-07 03:14:26',
                'updated_at' => '2026-05-13 11:47:34',
            ],
            [
                'id' => 10,
                'name' => 'Managing Director',
                'nik' => '838.01.23',
                'email' => 'md@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:26',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 10,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => null,
                'created_at' => '2026-05-07 03:14:26',
                'updated_at' => '2026-05-13 11:47:34',
            ],
            [
                'id' => 11,
                'name' => 'System Administrator',
                'nik' => '11',
                'email' => 'admin@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:26',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 12,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => null,
                'created_at' => '2026-05-07 03:14:26',
                'updated_at' => '2026-05-13 11:47:34',
            ],
            [
                'id' => 12,
                'name' => 'Accounting Officer',
                'nik' => '12',
                'email' => 'accounting@example.com',
                'profile_photo_path' => null,
                'email_verified_at' => '2026-05-07 03:14:26',
                'password' => '$2y$10$Xg/iq4YCCkIOCsG7SkcczunoDrDdNfm2OB8rPuW5g/JSVU9v/7CCa',
                'role_id' => 11,
                'is_available_for_approval' => 1,
                'remember_token' => null,
                'login_device_hash' => null,
                'created_at' => '2026-05-07 03:14:26',
                'updated_at' => '2026-05-13 11:47:34',
            ],

        ]);
    }
}