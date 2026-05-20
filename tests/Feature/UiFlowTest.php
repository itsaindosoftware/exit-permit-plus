<?php

namespace Tests\Feature;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $hrManager = $this->createUserWithRole('hr_manager', 'hr.manager@example.com');
        $sisca = $this->createUserWithRole('hr', 'payroll.hr@thaisummit.co.id');

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
            'hr_approver_id' => $hrManager->id,
            'hr_verified_by' => $hrManager->id,
            'hr_verified_at' => now(),
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
        $hrManager = $this->createUserWithRole('hr_manager', 'hr.manager2@example.com');
        $sisca = $this->createUserWithRole('hr', 'payroll.hr@thaisummit.co.id');

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
            'hr_approver_id' => $hrManager->id,
            'hr_verified_by' => $hrManager->id,
            'hr_verified_at' => now(),
        ]);

        $exitPermit->requestors()->create([
            'row_number' => 1,
            'name' => 'Budi Test',
            'employee_id' => 'EMP-001',
            'position' => 'Staff',
            'department' => 'BIPO',
            'reimburs_lunch_box' => null,
        ]);

        config()->set('attendance.source_disk', 'local');
        config()->set('attendance.source_path', 'attendance-sharing-test-meal');
        Storage::disk('local')->put(
            'attendance-sharing-test-meal/attendance.csv',
            "employee_id,name,checkin_time\nEMP-001,Budi Test," . now()->format('Y-m-d H:i:s') . "\n",
        );

        $response = $this
            ->actingAs($sisca)
            ->put(route('exit-permits.update', $exitPermit), []);

        $response->assertRedirect(route('exit-permits.index'));

        $exitPermit->refresh();
        $this->assertSame(ExitPermit::POST_MD_PATH_MEAL, $exitPermit->post_md_path);
        $this->assertTrue((bool) $exitPermit->has_valid_checkin);
        $this->assertNotNull($exitPermit->attendance_checked_at);
        $this->assertSame($sisca->id, $exitPermit->attendance_checked_by);
        $this->assertSame('Y', (string) $exitPermit->requestors()->first()?->reimburs_lunch_box);
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
            ->has('eligibleExitPermits', 2));
    }

    public function test_sisca_can_submit_exit_permit_meal_for_verified_bipo_data(): void
    {
        $requestor = $this->createUserWithRole('user', 'requestor-meal@example.com');
        $sisca = $this->createUserWithRole('hr', 'payroll.hr@thaisummit.co.id');
        $manager = $this->createUserWithRole('manager', 'manager-meal@example.com');
        $md = $this->createUserWithRole('md', 'md-meal@example.com');
        $hrManager = $this->createUserWithRole('hr_manager', 'hr.manager.meal@example.com');

        $exitPermit = ExitPermit::create([
            'user_id' => $requestor->id,
            'hr_approver_id' => $hrManager->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '16:00',
            'destination' => 'Customer Site',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Site visit',
            'notes' => '-',
            'status' => 'approved',
            'manager_approved_by' => $manager->id,
            'manager_approved_at' => now()->subHours(3),
            'md_approved_by' => $md->id,
            'md_approved_at' => now()->subHours(2),
            'hr_verified_by' => $hrManager->id,
            'hr_verified_at' => now()->subHour(),
            'attendance_checked_by' => $sisca->id,
            'attendance_checked_at' => now(),
            'has_valid_checkin' => true,
            'post_md_path' => ExitPermit::POST_MD_PATH_MEAL,
        ]);

        $exitPermit->requestors()->create([
            'row_number' => 1,
            'name' => 'Budi BIPO',
            'employee_id' => 'EMP-BIPO-01',
            'position' => 'Staff',
            'department' => 'BIPO',
            'reimburs_lunch_box' => 'Y',
        ]);

        $response = $this
            ->actingAs($sisca)
            ->post(route('exit-permit-meals.store'), [
                'exit_permit_id' => $exitPermit->id,
                'meal_date' => now()->toDateString(),
                'menu_name' => 'Nasi Ayam',
                'quantity' => 1,
                'actual_quantity' => 0,
                'visitor_count' => 0,
                'schedule_type' => 'single',
                'repeat_count' => 1,
                'notes' => 'Order by Sisca',
            ]);

        $response->assertRedirect(route('exit-permit-meals.index'));

        $this->assertDatabaseHas((new OrderMeal())->getTable(), [
            'user_id' => $sisca->id,
            'order_scope' => OrderMeal::SCOPE_EXIT_PERMIT,
            'exit_permit_id' => $exitPermit->id,
        ]);
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

        $response->assertSessionHasErrors('exit_permit_id');

        $eligibleExitPermit = ExitPermit::create([
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
            'hr_verified_by' => $user->id,
            'hr_verified_at' => now(),
            'attendance_checked_at' => now(),
            'has_valid_checkin' => true,
            'post_md_path' => ExitPermit::POST_MD_PATH_MEAL,
        ]);

        $eligibleExitPermit->requestors()->create([
            'row_number' => 1,
            'name' => 'Budi BIPO',
            'employee_id' => 'EMP-100',
            'position' => 'Staff',
            'department' => 'BIPO',
            'reimburs_lunch_box' => 'Y',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('exit-permit-meals.store'), [
                'exit_permit_id' => $eligibleExitPermit->id,
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

    public function test_md_approval_moves_document_to_hr_manager_stage(): void
    {
        $requestor = $this->createUserWithRole('user', 'requestor3@example.com');
        $manager = $this->createUserWithRole('manager', 'manager3@example.com');
        $md = $this->createUserWithRole('md', 'md3@example.com');
        $hrManager = $this->createUserWithRole('hr_manager', 'hr.manager3@example.com');

        $exitPermit = ExitPermit::create([
            'user_id' => $requestor->id,
            'hr_approver_id' => $hrManager->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '16:00',
            'destination' => 'Client Visit',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'vehicle_plate' => null,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Visit client',
            'notes' => '-',
            'status' => 'pending',
            'manager_approved_by' => $manager->id,
            'manager_approved_at' => now()->subHour(),
        ]);

        $response = $this
            ->actingAs($md)
            ->put(route('exit-permits.update', $exitPermit), [
                'status' => 'approved',
            ]);

        $response->assertRedirect(route('exit-permits.index'));

        $exitPermit->refresh();
        $this->assertSame('pending', $exitPermit->status);
        $this->assertNotNull($exitPermit->md_approved_at);
        $this->assertNull($exitPermit->hr_verified_at);
    }

    public function test_hr_manager_can_finalize_after_md_approval(): void
    {
        $requestor = $this->createUserWithRole('user', 'requestor4@example.com');
        $manager = $this->createUserWithRole('manager', 'manager4@example.com');
        $md = $this->createUserWithRole('md', 'md4@example.com');
        $hrManager = $this->createUserWithRole('hr_manager', 'hr.manager4@example.com');

        $exitPermit = ExitPermit::create([
            'user_id' => $requestor->id,
            'hr_approver_id' => $hrManager->id,
            'permit_date' => now()->toDateString(),
            'start_time' => '13:00',
            'end_time' => '16:00',
            'destination' => 'Vendor Meeting',
            'exit_type' => ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
            'vehicle_plate' => null,
            'returned_to_office' => true,
            'eligible_for_meal' => true,
            'reimbursement_amount' => 12000,
            'reason' => 'Meeting vendor',
            'notes' => '-',
            'status' => 'pending',
            'manager_approved_by' => $manager->id,
            'manager_approved_at' => now()->subHours(2),
            'md_approved_by' => $md->id,
            'md_approved_at' => now()->subHour(),
        ]);

        $response = $this
            ->actingAs($hrManager)
            ->put(route('exit-permits.update', $exitPermit), [
                'status' => 'approved',
            ]);

        $response->assertRedirect(route('exit-permits.index'));

        $exitPermit->refresh();
        $this->assertSame('approved', $exitPermit->status);
        $this->assertNotNull($exitPermit->hr_verified_at);
        $this->assertSame($hrManager->id, $exitPermit->hr_verified_by);
    }

    private function createUserWithRole(string $roleCode, string $email): User
    {
        $roleNames = [
            'user' => 'User',
            'hr' => 'HR',
            'hr_manager' => 'HR Manager',
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
