<?php

namespace Tests\Feature;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UiFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sisca_can_open_exit_permit_edit_for_attendance_verification(): void
    {
        $requestor = $this->createUserWithRole('user', 'requestor@example.com');
        $manager = $this->createUserWithRole('manager', 'manager@example.com');
        $md = $this->createUserWithRole('md', 'md@example.com');
        $sisca = $this->createUserWithRole('hr', 'sisca.dewiyani@example.com');

        $exitPermit = ExitPermit::create([
            'user_id' => $requestor->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '16:00',
            'destination' => 'Client Office',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'vehicle_plate' => null,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Meeting client',
            'notes' => 'N/A',
            'status' => 'approved',
            'manager_approved_by' => $manager->id,
            'manager_approved_at' => now()->subHour(),
            'md_approved_by' => $md->id,
            'md_approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($sisca)
            ->get(route('exit-permits.edit', $exitPermit));

        $response->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('ExitPermits/Edit')
            ->where('canVerifyAttendance', true)
            ->where('exitPermit.id', $exitPermit->id)
            ->where('exitPermit.status', 'approved'));
    }

    public function test_sisca_verification_sets_meal_path_when_checkin_exists_and_returned_to_office(): void
    {
        $requestor = $this->createUserWithRole('user', 'requestor2@example.com');
        $manager = $this->createUserWithRole('manager', 'manager2@example.com');
        $md = $this->createUserWithRole('md', 'md2@example.com');
        $sisca = $this->createUserWithRole('hr', 'sisca.dewiyani@example.com');

        $exitPermit = ExitPermit::create([
            'user_id' => $requestor->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'destination' => 'Supplier',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'vehicle_plate' => null,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Visit supplier',
            'notes' => '-',
            'status' => 'approved',
            'manager_approved_by' => $manager->id,
            'manager_approved_at' => now()->subHour(),
            'md_approved_by' => $md->id,
            'md_approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($sisca)
            ->put(route('exit-permits.update', $exitPermit), [
                'has_valid_checkin' => true,
            ]);

        $response->assertRedirect(route('exit-permits.index'));

        $exitPermit->refresh();
        $this->assertSame(ExitPermit::POST_MD_PATH_MEAL, $exitPermit->post_md_path);
        $this->assertTrue((bool) $exitPermit->has_valid_checkin);
        $this->assertNotNull($exitPermit->attendance_checked_at);
        $this->assertSame($sisca->id, $exitPermit->attendance_checked_by);
    }

    public function test_reimbursement_create_page_shows_only_eligible_exit_permits(): void
    {
        $user = $this->createUserWithRole('user', 'staff@example.com');

        $eligible = ExitPermit::create([
            'user_id' => $user->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'destination' => 'Clinic',
            'exit_type' => ExitPermit::EXIT_TYPE_SICK,
            'vehicle_plate' => null,
            'returned_to_office' => false,
            'eligible_for_meal' => false,
            'reimbursement_amount' => 12000,
            'reason' => 'Medical check',
            'notes' => '-',
            'status' => 'approved',
            'md_approved_at' => now(),
            'attendance_checked_at' => now(),
            'has_valid_checkin' => false,
            'post_md_path' => ExitPermit::POST_MD_PATH_REIMBURSEMENT,
        ]);

        ExitPermit::create([
            'user_id' => $user->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '15:00',
            'destination' => 'Customer',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'vehicle_plate' => null,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Meeting',
            'notes' => '-',
            'status' => 'approved',
            'md_approved_at' => now(),
            'attendance_checked_at' => now(),
            'has_valid_checkin' => true,
            'post_md_path' => ExitPermit::POST_MD_PATH_MEAL,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reimbursements.create'));

        $response->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('Reimbursements/Create')
            ->has('eligibleExitPermits', 1)
            ->where('eligibleExitPermits.0.id', $eligible->id));
    }

    public function test_general_order_meal_can_be_created_without_exit_permit_but_exit_permit_order_requires_verification(): void
    {
        $user = $this->createUserWithRole('user', 'meal-user@example.com');

        $response = $this
            ->actingAs($user)
            ->post(route('order-meals.store'), [
                'meal_date' => now()->toDateString(),
                'menu_name' => 'Nasi Ayam',
                'quantity' => 1,
                'actual_quantity' => 0,
                'visitor_count' => 0,
                'schedule_type' => 'single',
                'repeat_count' => 1,
                'notes' => '',
            ]);

        $response->assertRedirect(route('order-meals.index'));

        $this->assertDatabaseHas((new OrderMeal())->getTable(), [
            'user_id' => $user->id,
            'order_scope' => OrderMeal::SCOPE_GENERAL,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('exit-permit-meals.store'), [
                'meal_date' => now()->toDateString(),
                'menu_name' => 'Nasi Ayam',
                'quantity' => 1,
                'actual_quantity' => 0,
                'visitor_count' => 0,
                'schedule_type' => 'single',
                'repeat_count' => 1,
                'notes' => '',
            ]);

        $response->assertSessionHasErrors('meal_date');

        ExitPermit::create([
            'user_id' => $user->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '16:00',
            'destination' => 'Customer',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'vehicle_plate' => null,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Visit customer',
            'notes' => '-',
            'status' => 'approved',
            'md_approved_at' => now(),
            'attendance_checked_at' => now(),
            'has_valid_checkin' => true,
            'post_md_path' => ExitPermit::POST_MD_PATH_MEAL,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('exit-permit-meals.store'), [
                'meal_date' => now()->toDateString(),
                'menu_name' => 'Nasi Ayam',
                'quantity' => 1,
                'actual_quantity' => 0,
                'visitor_count' => 0,
                'schedule_type' => 'single',
                'repeat_count' => 1,
                'notes' => '',
            ]);

        $response->assertRedirect(route('exit-permit-meals.index'));
        $this->assertDatabaseHas((new OrderMeal())->getTable(), [
            'user_id' => $user->id,
            'order_scope' => OrderMeal::SCOPE_EXIT_PERMIT,
        ]);
    }

    private function createUserWithRole(string $roleCode, string $email): User
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
            'role_id' => $role->id,
            'is_available_for_approval' => true,
        ]);
    }
}
