<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $approvedExitPermits = ExitPermit::query()->where('status', 'approved');
        $lunchOrders = OrderMeal::query()->where('meal_type', 'lunch');
        $providedMealCount = (int) (clone $lunchOrders)->sum('quantity');
        $actualMealCount = (int) (clone $lunchOrders)->sum('actual_quantity');

        return Inertia::render('Dashboard', [
            'stats' => [
                'exitPermitCount' => ExitPermit::count(),
                'eligibleMealCount' => (clone $approvedExitPermits)->where('eligible_for_meal', true)->count(),
                'reimbursementTotal' => (int) (clone $approvedExitPermits)->sum('reimbursement_amount'),
                'providedMealCount' => $providedMealCount,
                'actualMealCount' => $actualMealCount,
                'remainingMealCount' => max(0, $providedMealCount - $actualMealCount),
            ],
            'mealTrend' => $this->mealTrend($lunchOrders),
        ]);
    }

    private function mealTrend($query): array
    {
        return (clone $query)
            ->selectRaw('meal_date, SUM(quantity) as provided_total, SUM(actual_quantity) as actual_total')
            ->groupBy('meal_date')
            ->orderByDesc('meal_date')
            ->limit(7)
            ->get()
            ->sortBy('meal_date')
            ->values()
            ->map(fn($row) => [
                'date' => (string) $row->meal_date,
                'provided' => (int) $row->provided_total,
                'actual' => (int) $row->actual_total,
                'remaining' => max(0, (int) $row->provided_total - (int) $row->actual_total),
            ])
            ->all();
    }
}
