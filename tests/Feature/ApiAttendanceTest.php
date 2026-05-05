<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_check_in_check_out_view_dashboard_and_logout(): void
    {
        $user = $this->createUserWithRole('user', 'mobile.user@example.com', 'password');

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'android',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role', 'role_name'],
            ]);

        $token = (string) $loginResponse->json('token');

        $dashboardBefore = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard');

        $dashboardBefore
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.attendance.check_in_at', null)
            ->assertJsonPath('data.attendance.check_out_at', null);

        $checkInResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendance/check-in');

        $checkInResponse
            ->assertOk()
            ->assertJsonPath('message', 'Absen masuk berhasil.')
            ->assertJsonPath('data.check_out_at', null);

        $checkOutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendance/check-out');

        $checkOutResponse
            ->assertOk()
            ->assertJsonPath('message', 'Absen pulang berhasil.');

        $dashboardAfter = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard');

        $dashboardAfter
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJson(fn($json) => $json
                ->whereType('data.attendance.check_in_at', 'string')
                ->whereType('data.attendance.check_out_at', 'string')
                ->etc());

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $logoutResponse
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.');

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_check_out_requires_check_in_first(): void
    {
        $user = $this->createUserWithRole('user', 'mobile.user2@example.com', 'password');

        $token = (string) $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/attendance/check-out')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Absen pulang tidak bisa dilakukan sebelum absen masuk.');
    }

    private function createUserWithRole(string $roleCode, string $email, string $password): User
    {
        $roleNames = [
            'user' => 'User',
            'hr' => 'HR',
            'manager' => 'Manager',
            'md' => 'Managing Director',
            'accounting' => 'Accounting',
            'admin' => 'Administrator',
        ];

        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => $roleNames[$roleCode] ?? ucfirst($roleCode)],
        );

        return User::factory()->create([
            'email' => $email,
            'password' => $password,
            'role_id' => $role->id,
            'is_available_for_approval' => true,
        ]);
    }
}
