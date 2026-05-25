<?php

namespace Tests\Unit;

use App\Http\Controllers\ExitPermitController;
use App\Http\Controllers\ReimbursementController;
use App\Models\ExitPermit;
use App\Models\OrderMeal;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OperationalRulesTest extends TestCase
{
    #[Test]
    public function exit_permit_before_noon_is_eligible_only_if_returned_before_lunch(): void
    {
        $eligiblePermit = new ExitPermit([
            'start_time' => '11:30',
            'end_time' => '11:50',
            'returned_to_office' => true,
        ]);

        $lateReturnPermit = new ExitPermit([
            'start_time' => '11:30',
            'end_time' => '12:30',
            'returned_to_office' => true,
        ]);

        $this->assertTrue($eligiblePermit->qualifiesForMeal());
        $this->assertFalse($lateReturnPermit->qualifiesForMeal());
    }

    #[Test]
    public function exit_permit_after_one_pm_is_eligible_when_employee_returns(): void
    {
        $permit = new ExitPermit([
            'start_time' => '13:15',
            'end_time' => '15:00',
            'returned_to_office' => true,
        ]);

        $permit->syncBusinessRules();

        $this->assertTrue($permit->eligible_for_meal);
    }

    #[Test]
    public function order_meal_remaining_quantity_is_calculated_from_actual_consumption(): void
    {
        $orderMeal = new OrderMeal([
            'quantity' => 120,
            'actual_quantity' => 100,
        ]);

        $this->assertSame(20, $orderMeal->remaining_quantity);
    }

    #[Test]
    public function reimbursement_manager_scope_is_role_sensitive_for_wida(): void
    {
        $controller = new ReimbursementController();
        $managerRole = new Role(['code' => 'manager', 'name' => 'Manager']);
        $hrManagerRole = new Role(['code' => 'hr_manager', 'name' => 'HR Manager']);

        $widaAsManager = $this->makeUser('Wida Mustika Sari', '115.08.08', $managerRole, $hrManagerRole);
        $widaAsHrManager = $this->makeUser('Wida Mustika Sari', '115.08.08', $hrManagerRole, $managerRole);

        $managerScope = $this->invokePrivateMethod($controller, 'reimbursementApprovalScopeForUser', [$widaAsManager]);
        $hrManagerScope = $this->invokePrivateMethod($controller, 'reimbursementApprovalScopeForUser', [$widaAsHrManager]);

        $this->assertSame(['Dede Susilawati'], $managerScope['creators']);
        $this->assertSame(['HR', 'HR & SYD IT', 'HR, GA & LEGAL', 'SYD & IT'], $managerScope['departments']);
        $this->assertSame(['Dede Susilawati'], $hrManagerScope['creators']);
        $this->assertSame([], $hrManagerScope['departments']);
    }

    #[Test]
    public function exit_permit_manager_scope_matches_eltha_alvin_ppic(): void
    {
        $controller = app()->make(ExitPermitController::class);
        $managerRole = new Role(['code' => 'manager', 'name' => 'Manager']);
        $user = $this->makeUser('Eltha Restu Sedio Laksono', '965.04.26', $managerRole);
        $exitPermit = $this->makeExitPermit('Alvin Dhuhalkarim', 'HAP-PMG-017', ['PPIC & Sales Delivery (Outbound)', 'PPIC & Sales Delivery (Outbound)']);
        $exitPermit->status = 'pending';
        $exitPermit->manager_approved_at = null;

        $result = $this->invokePrivateMethod($controller, 'canSubmitApproval', [$exitPermit, $user]);

        $this->assertTrue($result);
    }

    #[Test]
    public function reimbursement_manager_scope_matches_the_expected_creator_and_department_rules(): void
    {
        $controller = new ReimbursementController();
        $managerRole = new Role(['code' => 'manager', 'name' => 'Manager']);
        $hrManagerRole = new Role(['code' => 'hr_manager', 'name' => 'HR Manager']);

        $cases = [
            ['631.08.14', 'manager', 'Indriani', '631.08.14', ['Production'], true],
            ['631.08.14', 'manager', 'Indriani', '631.08.14', ['Finance'], false],
            ['965.04.26', 'manager', 'Alvin Dhuhalkarim', '965.04.26', ['PPIC & Sales Delivery (Outbound)'], true],
            ['019.05.07', 'manager', 'Yulianto Abdurrahman Affandi', '019.05.07', ['Accounting, Finance & CIC'], true],
            ['115.08.08', 'manager', 'Dede Susilawati', '115.08.08', ['HR, GA & LEGAL', 'SYD & IT'], true],
            ['115.08.08', 'manager', 'Dede Susilawati', '115.08.08', ['Finance'], false],
            ['115.08.08', 'hr_manager', 'Dede Susilawati', '115.08.08', ['Finance'], true],
        ];

        foreach ($cases as [$nik, $roleCode, $creatorName, $ownerNik, $departments, $expected]) {
            $user = $this->makeUser('Approver ' . $nik, $nik, $roleCode === 'hr_manager' ? $hrManagerRole : $managerRole);
            $reimbursement = $this->makeReimbursement($creatorName, $ownerNik, $departments);

            $scope = $this->invokePrivateMethod($controller, 'reimbursementApprovalScopeForUser', [$user]);
            $result = $this->invokePrivateMethod($controller, 'reimbursementMatchesManagerScope', [$reimbursement, $scope]);

            $this->assertSame($expected, $result, sprintf('Unexpected scope result for approver %s with departments %s', $nik, implode(', ', $departments)));
        }
    }

    private function makeUser(string $name, string $nik, Role $role, ?Role $secondaryRole = null): User
    {
        $user = new User([
            'name' => $name,
            'nik' => $nik,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
        ]);

        $user->setRelation('role', $role);

        if ($secondaryRole) {
            $user->setRelation('secondaryRole', $secondaryRole);
        }

        return $user;
    }

    private function makeReimbursement(string $creatorName, string $creatorNik, array $departments): Reimbursement
    {
        $reimbursement = new Reimbursement();
        $reimbursement->setRelation('user', new User([
            'name' => $creatorName,
            'nik' => $creatorNik,
        ]));

        $exitPermit = new ExitPermit();
        $exitPermit->setRelation('requestors', new EloquentCollection(array_map(
            static fn(string $department) => (object) ['department' => $department],
            $departments,
        )));

        $reimbursement->setRelation('exitPermit', $exitPermit);

        return $reimbursement;
    }

    private function makeExitPermit(string $creatorName, string $creatorNik, array $departments): ExitPermit
    {
        $exitPermit = new ExitPermit();
        $exitPermit->setRelation('user', new User([
            'name' => $creatorName,
            'nik' => $creatorNik,
        ]));
        $exitPermit->setRelation('requestors', new EloquentCollection(array_map(
            static fn(string $department) => (object) ['department' => $department],
            $departments,
        )));

        return $exitPermit;
    }

    private function invokePrivateMethod(object $object, string $methodName, array $arguments = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}