<?php

namespace Tests\Unit;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
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
}