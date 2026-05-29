<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn() => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'profile_photo_url' => $request->user()->profile_photo_path
                            ? asset('storage/' . ltrim((string) $request->user()->profile_photo_path, '/'))
                            : null,
                        'role' => $request->user()->role,
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
            ],
            'notifications' => [
                'unread' => fn() => $request->user()
                    ? $request->user()->unreadNotifications()
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(fn($notification) => [
                            'id' => $notification->id,
                            'title' => $notification->data['title'] ?? 'Notifikasi',
                            'message' => $notification->data['message'] ?? '',
                            'created_at' => optional($notification->created_at)->toDateTimeString(),
                            'payload' => $notification->data,
                        ])
                        ->values()
                        ->all()
                    : [],
                'unread_count' => fn() => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
                'schedule_car_count' => fn() => $request->user() && strtolower((string) $request->user()->email) === 'hrga-01@example.com'
                    ? \App\Models\ExitPermit::query()
                        ->whereIn('exit_type', [
                            \App\Models\ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
                            \App\Models\ExitPermit::EXIT_TYPE_ASSIGNMENT,
                            \App\Models\ExitPermit::EXIT_TYPE_COMPANY,
                        ])
                        ->where('order_car', true)
                        ->where('status', 'pending')
                        ->where(function ($q) {
                            $q->whereNull('vehicle_plate')->orWhereNull('driver_name');
                        })->count()
                    : 0,
                'exit_permit_approval_count' => fn() => $this->exitPermitApprovalCount($request->user()),
                'reimbursement_approval_count' => fn() => $request->user() ? (function () use ($request) {
                    $user = $request->user();
                    return $this->reimbursementApprovalCount($user);
                })() : 0,
            ],
        ];
    }

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
        '9280125' => [
            'creators' => ['Rydha Ramlan Gunawan'],
            'departments' => [
                'Maintenance Dies',
            ],
        ],
    ];

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
            $model = $query->getModel();

            if ($model instanceof \App\Models\ExitPermit) {
                $query->whereDoesntHave('requestors', function ($requestorQuery) use ($departments) {
                    $requestorQuery->whereNotIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(COALESCE(department, "")))'), $departments);
                });
            } else {
                $query->whereHas('exitPermit', function ($exitPermitQuery) use ($departments) {
                    $exitPermitQuery->whereDoesntHave('requestors', function ($requestorQuery) use ($departments) {
                        $requestorQuery->whereNotIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(COALESCE(department, "")))'), $departments);
                    });
                });
            }
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

    private function reimbursementApprovalCount($user): int
    {
        $roleCode = $user->role?->code;
        $email = strtolower((string) $user->email);

        if (in_array($roleCode, ['manager', 'hr_manager'], true)) {
            $query = \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_PENDING_MANAGER);

            $this->applyManagerApprovalScope($query, $user);

            return $query->count();
        }

        if ($roleCode === 'md') {
            return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_PENDING_MD)->count();
        }

        if ($roleCode === 'hr' && $email === 'hrga-01@example.com') {
            return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_PENDING_RATNA)->count();
        }

        if ($roleCode === 'accounting') {
            return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING)->count();
        }

        return 0;
    }

    private function exitPermitApprovalCount($user): int
    {
        if (!$user) {
            return 0;
        }

        $roleCode = $user->role?->code;
        $isDualApprovalUser = (bool) ($user->isWidaMustikaSari() ?? false);
        $query = \App\Models\ExitPermit::query();

        if ($isDualApprovalUser) {
            $query->where(function ($subQuery) use ($user) {
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

            return $query->count();
        }

        if ($roleCode === 'manager') {
            $this->applyManagerApprovalScope($query->whereNull('manager_approved_at')->where('status', 'pending'), $user);
            return $query->count();
        }

        if ($roleCode === 'md') {
            return $query->whereNotNull('manager_approved_at')
                ->whereNull('md_approved_at')
                ->where('status', 'pending')
                ->count();
        }

        if ($roleCode === 'hr_manager') {
            return $query->whereNotNull('manager_approved_at')
                ->whereNotNull('md_approved_at')
                ->whereNull('hr_verified_at')
                ->where('status', 'pending')
                ->count();
        }

        // ini  untuk atur logic  jumlah notifikasi hr sisca
        if ($roleCode === 'hr' && strtolower((string) $user->email) === 'payroll.hr@example.com') {
            return $query->where('status', 'approved')
                ->whereNotNull('md_approved_at')
                ->whereNotNull('hr_verified_at')
                ->whereNull('attendance_checked_at')
                ->count();
        }

        return 0;
    }
}
