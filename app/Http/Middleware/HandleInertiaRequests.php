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
                'schedule_car_count' => fn() => $request->user() && strtolower((string) $request->user()->email) === 'ratna@example.com'
                    ? \App\Models\ExitPermit::query()
                        ->where('exit_type', \App\Models\ExitPermit::EXIT_TYPE_BUSINESS_TRIP)
                        ->where('order_car', true)
                        ->where('status', 'pending')
                        ->where(function ($q) {
                            $q->whereNull('vehicle_plate')->orWhereNull('driver_name');
                        })->count()
                    : 0,
                'exit_permit_approval_count' => fn() => $request->user() ? (function () use ($request) {
                    $user = $request->user();
                    $roleCode = $user->role?->code;
                    $query = \App\Models\ExitPermit::query();

                    if ($roleCode === 'manager') {
                        return $query->whereNull('manager_approved_at')->where('status', 'pending')->count();
                    } elseif ($roleCode === 'md') {
                        return $query->whereNotNull('manager_approved_at')
                            ->whereNull('md_approved_at')
                            ->where('status', 'pending')->count();
                    } elseif ($roleCode === 'hr_manager') {
                        return $query->whereNotNull('manager_approved_at')
                            ->whereNotNull('md_approved_at')
                            ->whereNull('hr_verified_at')
                            ->where('status', 'pending')->count();
                    } elseif ($roleCode === 'hr' && strtolower((string) $user->email) === 'payroll.hr@thaisummit.co.id') {
                        return $query->where('status', 'approved')
                            ->where('exit_type', \App\Models\ExitPermit::EXIT_TYPE_BUSINESS_TRIP)
                            ->whereNotNull('md_approved_at')
                            ->whereNotNull('hr_verified_at')
                            ->whereNull('attendance_checked_at')->count();
                    }

                    return 0;
                })() : 0,
                'reimbursement_approval_count' => fn() => $request->user() ? (function () use ($request) {
                    $user = $request->user();
                    $roleCode = $user->role?->code;
                    $email = strtolower((string) $user->email);

                    if (in_array($roleCode, ['manager', 'hr_manager'], true)) {
                        return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_PENDING_MANAGER)->count();
                    } elseif ($roleCode === 'md') {
                        return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_PENDING_MD)->count();
                    } elseif ($roleCode === 'hr' && $email === 'ratna@example.com') {
                        return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_PENDING_RATNA)->count();
                    } elseif ($roleCode === 'accounting') {
                        return \App\Models\Reimbursement::query()->where('status', \App\Models\Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING)->count();
                    }

                    return 0;
                })() : 0,
            ],
        ];
    }
}
