<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
use App\Models\Reimbursement;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const ATTENDANCE_VERIFIER_EMAIL = 'sisca@example.com';

    private const MANAGER_APPROVAL_SCOPES = [
        '6310814' => [
            'creators' => ['Indriani', 'Admin Prod'],
            'departments' => [
                'Production',
                'Quality',
                'All Prod Section',
                'Quality Assurance',
                'Production Engineer',
                'Production Administration',
            ],
        ],
        '9650426' => [
            'creators' => ['Alvin Dhuhalkarim'],
            'departments' => ['PPIC', 'PPIC & Sales Delivery (Outbound)', 'PPIC & Sales Delivery (Inbound)'],
        ],
        '0190507' => [
            'creators' => ['Yulianto Abdurrahman Affandi'],
            'departments' => ['Accounting', 'Accounting, Finance & CIC'],
        ],
        '1150808' => [
            'creators' => ['Dede Susilawati'],
            'departments' => ['HR', 'HR & SYD IT', 'HR, GA & LEGAL', 'SYD & IT'],
        ],
    ];

    public function __invoke(): Response
    {
        $user = request()->user();
        $userId = (int) ($user?->id ?? 0);
        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $roleCode = $user?->role?->code;
        $canViewMealAnalytics = in_array($user?->role?->code, ['hr', 'manager', 'md', 'hr_manager', 'admin'], true);
        $canAccessExitPermitApproval = in_array($roleCode, ['manager', 'md', 'hr_manager', 'admin'], true)
            || ($roleCode === 'hr' && strtolower((string) $user?->email) === self::ATTENDANCE_VERIFIER_EMAIL);
        $isDualApprovalUser = (bool) ($user?->isWidaMustikaSari() ?? false);

        $myExitPermits = ExitPermit::query()->where('user_id', $userId);
        $myExitPermitsThisMonth = (clone $myExitPermits)->whereBetween('permit_date', [$monthStart, $monthEnd]);
        $approvedExitPermits = ExitPermit::query()->where('status', 'approved');
        $lunchOrders = OrderMeal::query()->where('meal_type', 'lunch');
        $providedMealCount = (int) (clone $lunchOrders)->sum('quantity');
        $actualMealCount = (int) (clone $lunchOrders)->sum('actual_quantity');
        $approvalQuery = ExitPermit::query();
        $pendingReimbursementQuery = Reimbursement::query()
            ->where('user_id', $userId)
            ->whereNotIn('status', [
                Reimbursement::STATUS_FINISHED,
                Reimbursement::STATUS_REJECTED,
            ]);

        $recentExitPermits = (clone $myExitPermits)
            ->with('requestors:id,exit_permit_id,name')
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (ExitPermit $exitPermit) {
                $requestorName = (string) ($exitPermit->requestors->first()?->name ?? '-');

                return [
                    'id' => $exitPermit->id,
                    'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                    'destination' => (string) ($exitPermit->destination ?? '-'),
                    'requestor_name' => $requestorName,
                    'status' => (string) ($exitPermit->status ?? 'pending'),
                    'stage' => $this->approvalStageForOwner($exitPermit),
                ];
            })
            ->values()
            ->all();

        $approvalStageCounts = [
            'manager' => (clone $myExitPermits)
                ->where('status', 'pending')
                ->whereNull('manager_approved_at')
                ->count(),
            'md' => (clone $myExitPermits)
                ->where('status', 'pending')
                ->whereNotNull('manager_approved_at')
                ->whereNull('md_approved_at')
                ->count(),
            'hr_manager' => (clone $myExitPermits)
                ->where('status', 'pending')
                ->whereNotNull('manager_approved_at')
                ->whereNotNull('md_approved_at')
                ->whereNull('hr_verified_at')
                ->count(),
        ];

        if ($isDualApprovalUser) {
            $approvalQuery->where(function ($subQuery) use ($user) {
                $subQuery->where(function ($managerQuery) use ($user) {
                    $managerQuery->whereNull('manager_approved_at')->where('status', 'pending');
                    $this->applyManagerApprovalScope($managerQuery, $user);
                })->orWhere(function ($hrManagerQuery) {
                    $hrManagerQuery->whereNotNull('manager_approved_at')
                        ->whereNotNull('md_approved_at')
                        ->whereNull('hr_verified_at')
                        ->where('status', 'pending');
                });
            });
        } elseif ($roleCode === 'manager') {
            $approvalQuery->whereNull('manager_approved_at')
                ->where('status', 'pending');
            $this->applyManagerApprovalScope($approvalQuery, $user);
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
                'exitPermitThisMonthCount' => (clone $myExitPermitsThisMonth)->count(),
                'exitPermitApprovalCount' => $canAccessExitPermitApproval ? (clone $approvalQuery)->count() : 0,
                'pendingApprovalMyCount' => array_sum($approvalStageCounts),
                'eligibleMealCount' => (clone $approvedExitPermits)->where('eligible_for_meal', true)->count(),
                'reimbursementTotal' => (int) Reimbursement::query()
                    ->join('exit_permits', 'exit_permits.id', '=', 'reimbursements.exit_permit_id')
                    ->where('exit_permits.user_id', $userId)
                    ->where('reimbursements.user_id', $userId)
                    ->where('reimbursements.status', Reimbursement::STATUS_FINISHED)
                    ->sum('reimbursements.amount'),
                'reimbursementPendingCount' => (clone $pendingReimbursementQuery)->count(),
                'reimbursementThisMonthApprovedTotal' => (int) Reimbursement::query()
                    ->where('user_id', $userId)
                    ->whereBetween('request_date', [$monthStart, $monthEnd])
                    ->where('status', Reimbursement::STATUS_FINISHED)
                    ->sum('amount'),
                'providedMealCount' => $providedMealCount,
                'actualMealCount' => $actualMealCount,
                'remainingMealCount' => max(0, $providedMealCount - $actualMealCount),
                'monthLabel' => Carbon::parse($monthStart)->translatedFormat('F Y'),
            ],
            'approvalStageCounts' => $approvalStageCounts,
            'recentExitPermits' => $recentExitPermits,
            'mealTrend' => $canViewMealAnalytics ? $this->mealTrend($lunchOrders) : [],
        ]);
    }

    private function approvalStageForOwner(ExitPermit $exitPermit): string
    {
        if ($exitPermit->status === 'approved') {
            return 'Approved';
        }

        if ($exitPermit->status === 'rejected') {
            return 'Rejected';
        }

        if (!$exitPermit->manager_approved_at) {
            return 'Waiting Manager';
        }

        if (!$exitPermit->md_approved_at) {
            return 'Waiting MD';
        }

        if (!$exitPermit->hr_verified_at) {
            return 'Waiting HR Manager';
        }

        return 'Pending';
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

    private function applyManagerApprovalScope($query, $user): void
    {
        $scope = $this->managerApprovalScopeForUser($user);

        if (!$scope) {
            return;
        }

        $creatorNames = array_values(array_filter(array_map(
            fn($value) => strtolower(trim((string) $value)),
            (array) ($scope['creators'] ?? []),
        )));
        $departments = array_values(array_filter(array_map(
            fn($value) => strtolower(trim((string) $value)),
            (array) ($scope['departments'] ?? []),
        )));

        if ($creatorNames !== []) {
            $query->whereHas('user', function ($userQuery) use ($creatorNames) {
                $userQuery->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(name))'), $creatorNames);
            });
        }

        if ($departments !== []) {
            $query->whereDoesntHave('requestors', function ($requestorQuery) use ($departments) {
                $requestorQuery->whereNotIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(COALESCE(department, "")))'), $departments);
            });
        }
    }

    private function managerApprovalScopeForUser($user): ?array
    {
        $approverKey = strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) ($user?->nik ?? ''))) ?? '');
        $scope = self::MANAGER_APPROVAL_SCOPES[$approverKey] ?? null;

        if (!$scope) {
            return null;
        }

        if ($approverKey === '1150808' && $user?->role?->code === 'hr_manager') {
            return [
                'creators' => $scope['creators'] ?? [],
                'departments' => [],
            ];
        }

        return $scope;
    }

    private function toDateOnly(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
