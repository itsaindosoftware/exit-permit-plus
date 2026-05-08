<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
use App\Models\Reimbursement;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const ATTENDANCE_VERIFIER_EMAIL = 'sisca.dewiyani@example.com';

    public function __invoke(): Response
    {
        $user = request()->user();
        $userId = (int) ($user?->id ?? 0);
        $roleCode = $user?->role?->code;
        $canViewMealAnalytics = in_array($user?->role?->code, ['hr', 'manager', 'md', 'hr_manager'], true);
        $canAccessExitPermitApproval = in_array($roleCode, ['manager', 'md', 'hr_manager'], true)
            || ($roleCode === 'hr' && strtolower((string) $user?->email) === self::ATTENDANCE_VERIFIER_EMAIL);

        $approvedExitPermits = ExitPermit::query()->where('status', 'approved');
        $lunchOrders = OrderMeal::query()->where('meal_type', 'lunch');
        $providedMealCount = (int) (clone $lunchOrders)->sum('quantity');
        $actualMealCount = (int) (clone $lunchOrders)->sum('actual_quantity');
        $approvalQuery = ExitPermit::query();

        if ($roleCode === 'manager') {
            $approvalQuery->whereNull('manager_approved_at')->where('status', 'pending');
        } elseif ($roleCode === 'md') {
            $approvalQuery->whereNotNull('manager_approved_at')
                ->whereNull('md_approved_at')
                ->where('status', 'pending');
        } elseif ($roleCode === 'hr_manager') {
            $approvalQuery->whereNotNull('manager_approved_at')
                ->whereNotNull('md_approved_at')
                ->whereNull('hr_verified_at')
                ->where('status', 'pending');
        }

        return Inertia::render('Dashboard', [
            'viewerRole' => $user?->role?->code,
            'canViewMealAnalytics' => $canViewMealAnalytics,
            'canAccessExitPermitApproval' => $canAccessExitPermitApproval,
            'stats' => [
                'exitPermitCount' => ExitPermit::query()
                    ->where('user_id', $userId)
                    ->count(),
                'exitPermitApprovalCount' => $canAccessExitPermitApproval ? (clone $approvalQuery)->count() : 0,
                'eligibleMealCount' => (clone $approvedExitPermits)->where('eligible_for_meal', true)->count(),
                'reimbursementTotal' => (int) Reimbursement::query()
                    ->join('exit_permits', 'exit_permits.id', '=', 'reimbursements.exit_permit_id')
                    ->where('exit_permits.user_id', $userId)
                    ->where('reimbursements.user_id', $userId)
                    ->where('reimbursements.status', Reimbursement::STATUS_FINISHED)
                    ->sum('reimbursements.amount'),
                'providedMealCount' => $providedMealCount,
                'actualMealCount' => $actualMealCount,
                'remainingMealCount' => max(0, $providedMealCount - $actualMealCount),
            ],
            'mealTrend' => $canViewMealAnalytics ? $this->mealTrend($lunchOrders) : [],
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
