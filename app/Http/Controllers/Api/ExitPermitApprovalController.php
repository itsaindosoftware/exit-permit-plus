<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExitPermit;
use App\Models\User;
use App\Notifications\ExitPermitApprovalRequested;
use App\Notifications\ExitPermitStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExitPermitApprovalController extends Controller
{
    private const ATTENDANCE_VERIFIER_EMAIL = 'payroll.hr@example.com';

    private const HR_APPROVER_PRIORITY_EMAILS = [
        'hr.manager@example.com',
        'wida.mustika.sari@example.com',
        'wida.mus@example.com',
        'theresia.saing@example.com',
        'hrga-01@example.com',
        'payroll.hr@example.com',
    ];

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
            'creators' => ['Alvin Dhuhalkarim', 'HAP-PMG-017'],
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

    public function submit(Request $request, ExitPermit $exitPermit): JsonResponse
    {
        return $this->processApproval($request, $exitPermit, $this->validateStatus($request));
    }

    public function approve(Request $request, ExitPermit $exitPermit): JsonResponse
    {
        return $this->processApproval($request, $exitPermit, 'approved');
    }

    public function reject(Request $request, ExitPermit $exitPermit): JsonResponse
    {
        return $this->processApproval($request, $exitPermit, 'rejected');
    }

    public function show(Request $request, ExitPermit $exitPermit): JsonResponse
    {
        $user = $request->user();

        if (!$this->canViewExitPermit($exitPermit, $user)) {
            return response()->json([
                'message' => 'You do not have access to view this exit permit.',
            ], 403);
        }

        return response()->json([
            'data' => $this->exitPermitPayload($exitPermit),
        ]);
    }

    private function processApproval(Request $request, ExitPermit $exitPermit, string $status): JsonResponse
    {
        $user = $request->user();

        if (!$this->canSubmitApproval($exitPermit, $user)) {
            return response()->json([
                'message' => 'You do not have access to approve this exit permit.',
            ], 403);
        }

        $roleCode = $user?->role?->code;
        $isDualApprovalUser = (bool) ($user?->isWidaMustikaSari() ?? false);

        if ($roleCode === 'manager' && !($isDualApprovalUser && $exitPermit->manager_approved_at && $exitPermit->md_approved_at && !$exitPermit->hr_verified_at)) {
            if (!$this->canUserApproveManagerStage($exitPermit, $user)) {
                return response()->json([
                    'message' => 'You do not have approval scope for this exit permit.',
                ], 403);
            }

            if ($exitPermit->manager_approved_at || $exitPermit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Manager approval has already been processed.',
                ]);
            }

            $this->fillApprovalData($exitPermit, 'manager');

            if ($status === 'approved') {
                $hrApproverId = $this->resolveHrApproverId();

                if (!$hrApproverId) {
                    throw ValidationException::withMessages([
                        'status' => 'Tiered HR PIC not found. Please contact the Administrator.',
                    ]);
                }

                $exitPermit->hr_approver_id = $hrApproverId;
                $exitPermit->status = 'pending';

                $hrUser = User::query()->find($hrApproverId);
                if ($hrUser) {
                    $this->notifyExitPermitApproverOnce($hrUser, $exitPermit, 'hr_manager');
                }

                $mds = User::query()
                    ->whereHas('role', fn($q) => $q->where('code', 'md'))
                    ->where('is_available_for_approval', true)
                    ->get();

                foreach ($mds as $md) {
                    $this->notifyExitPermitApproverOnce($md, $exitPermit, 'md');
                }

                $this->notifyExitPermitOwner($exitPermit, 'manager_approved');
            } else {
                $exitPermit->status = 'rejected';
            }
        }

        if ($roleCode === 'md') {
            if (!$exitPermit->manager_approved_at) {
                throw ValidationException::withMessages([
                    'status' => 'MD approval can only be done after manager approval.',
                ]);
            }

            if ($exitPermit->md_approved_at || $exitPermit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'MD approval has already been processed or the document is finalized.',
                ]);
            }

            $this->fillApprovalData($exitPermit, 'md');

            if ($status === 'approved') {
                $hrApproverId = $exitPermit->hr_approver_id ?: $this->resolveHrApproverId();

                if (!$hrApproverId) {
                    throw ValidationException::withMessages([
                        'status' => 'HR Manager approver not found. Please contact the Administrator.',
                    ]);
                }

                $exitPermit->hr_approver_id = $hrApproverId;
                $exitPermit->status = 'pending';
                $exitPermit->hr_verified_by = null;
                $exitPermit->hr_verified_at = null;
                $exitPermit->attendance_checked_by = null;
                $exitPermit->attendance_checked_at = null;
                $exitPermit->has_valid_checkin = null;
                $exitPermit->post_md_path = null;

                $this->notifyExitPermitOwner($exitPermit, 'md_approved');
            } else {
                $exitPermit->status = 'rejected';
            }
        }

        if ($roleCode === 'hr_manager' || ($isDualApprovalUser && $exitPermit->manager_approved_at && $exitPermit->md_approved_at && !$exitPermit->hr_verified_at)) {
            if (!$exitPermit->manager_approved_at || !$exitPermit->md_approved_at) {
                throw ValidationException::withMessages([
                    'status' => 'HR Manager approval can only be done after MD approval.',
                ]);
            }

            if ($exitPermit->status !== 'pending' || $exitPermit->hr_verified_at) {
                throw ValidationException::withMessages([
                    'status' => 'HR Manager approval has already been processed or the document is finalized.',
                ]);
            }

            if (!$isDualApprovalUser && $exitPermit->hr_approver_id && $exitPermit->hr_approver_id !== $user?->id) {
                throw ValidationException::withMessages([
                    'status' => 'This document is assigned to another HR Manager as the approver.',
                ]);
            }

            $exitPermit->hr_verified_by = $user?->id;
            $exitPermit->hr_verified_at = now();
            $exitPermit->status = $status;

            if ($status === 'approved') {
                $exitPermit->attendance_checked_by = null;
                $exitPermit->attendance_checked_at = null;
                $exitPermit->has_valid_checkin = null;
                $exitPermit->post_md_path = null;

                $sisca = User::query()
                    ->where('email', self::ATTENDANCE_VERIFIER_EMAIL)
                    ->first();

                if ($sisca) {
                    $sisca->notify(new ExitPermitApprovalRequested($exitPermit, 'attendance_verifier'));
                }

                $this->notifyExitPermitOwner($exitPermit, 'hr_manager_approved');
            }
        }

        if (!$exitPermit->isDirty()) {
            return response()->json([
                'message' => 'No approval changes were applied.',
            ], 422);
        }

        $exitPermit->save();

        return response()->json([
            'message' => 'Exit permit approval has been processed.',
            'data' => $this->exitPermitPayload($exitPermit),
        ]);
    }

    private function applyAdminApproval(ExitPermit $exitPermit, $user, string $status): void
    {
        if ($exitPermit->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Exit permit approval has already been finalized.',
            ]);
        }

        if (!$exitPermit->manager_approved_at) {
            $this->fillApprovalData($exitPermit, 'manager');

            if ($status === 'approved') {
                $hrApproverId = $this->resolveHrApproverId();

                if (!$hrApproverId) {
                    throw ValidationException::withMessages([
                        'status' => 'Tiered HR PIC not found. Please contact the Administrator.',
                    ]);
                }

                $exitPermit->hr_approver_id = $hrApproverId;
                $exitPermit->status = 'pending';

                $hrUser = User::query()->find($hrApproverId);
                if ($hrUser) {
                    $this->notifyExitPermitApproverOnce($hrUser, $exitPermit, 'hr_manager');
                }

                $mds = User::query()
                    ->whereHas('role', fn($q) => $q->where('code', 'md'))
                    ->where('is_available_for_approval', true)
                    ->get();

                foreach ($mds as $md) {
                    $this->notifyExitPermitApproverOnce($md, $exitPermit, 'md');
                }

                $this->notifyExitPermitOwner($exitPermit, 'manager_approved');
            } else {
                $exitPermit->status = 'rejected';
            }

            return;
        }

        if (!$exitPermit->md_approved_at) {
            $this->fillApprovalData($exitPermit, 'md');

            if ($status === 'approved') {
                $hrApproverId = $exitPermit->hr_approver_id ?: $this->resolveHrApproverId();

                if (!$hrApproverId) {
                    throw ValidationException::withMessages([
                        'status' => 'HR Manager approver not found. Please contact the Administrator.',
                    ]);
                }

                $exitPermit->hr_approver_id = $hrApproverId;
                $exitPermit->status = 'pending';
                $exitPermit->hr_verified_by = null;
                $exitPermit->hr_verified_at = null;
                $exitPermit->attendance_checked_by = null;
                $exitPermit->attendance_checked_at = null;
                $exitPermit->has_valid_checkin = null;
                $exitPermit->post_md_path = null;

                $this->notifyExitPermitOwner($exitPermit, 'md_approved');
            } else {
                $exitPermit->status = 'rejected';
            }

            return;
        }

        if (!$exitPermit->hr_verified_at) {
            $exitPermit->hr_verified_by = $user?->id;
            $exitPermit->hr_verified_at = now();
            $exitPermit->status = $status;

            if ($status === 'approved') {
                $exitPermit->attendance_checked_by = null;
                $exitPermit->attendance_checked_at = null;
                $exitPermit->has_valid_checkin = null;
                $exitPermit->post_md_path = null;

                $sisca = User::query()
                    ->where('email', self::ATTENDANCE_VERIFIER_EMAIL)
                    ->first();

                if ($sisca) {
                    $sisca->notify(new ExitPermitApprovalRequested($exitPermit, 'attendance_verifier'));
                }

                $this->notifyExitPermitOwner($exitPermit, 'hr_manager_approved');
            }

            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Exit permit approval has already been finalized.',
        ]);
    }

    private function validateStatus(Request $request): string
    {
        if (!$request->filled('status')) {
            return 'approved';
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        return (string) $validated['status'];
    }

    private function fillApprovalData(ExitPermit $exitPermit, string $roleCode): void
    {
        if ($roleCode === 'manager') {
            $exitPermit->manager_approved_by = auth()->id();
            $exitPermit->manager_approved_at = now();
        }

        if ($roleCode === 'md') {
            $exitPermit->md_approved_by = auth()->id();
            $exitPermit->md_approved_at = now();
        }
    }

    private function notifyExitPermitOwner(ExitPermit $exitPermit, string $stage): void
    {
        $exitPermit->loadMissing('user:id,name,email');
        $owner = $exitPermit->user;

        if (!$owner || !filled($owner->email)) {
            return;
        }

        $owner->notify(new ExitPermitStatusUpdated($exitPermit, $stage));
    }

    private function notifyExitPermitApproverOnce(User $approver, ExitPermit $exitPermit, string $stage): void
    {
        $alreadyNotified = $approver->notifications()
            ->where('type', ExitPermitApprovalRequested::class)
            ->where('data->exit_permit_id', $exitPermit->id)
            ->where('data->stage', $stage)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $approver->notify(new ExitPermitApprovalRequested($exitPermit, $stage));
    }

    private function resolveHrApproverId(): ?int
    {
        $approversByEmail = User::query()
            ->select(['id', 'email', 'is_available_for_approval'])
            ->whereIn('email', self::HR_APPROVER_PRIORITY_EMAILS)
            ->whereHas('role', fn($roleQuery) => $roleQuery->whereIn('code', ['manager', 'hr_manager', 'hr']))
            ->get()
            ->keyBy(fn(User $user) => strtolower((string) $user->email));

        foreach (self::HR_APPROVER_PRIORITY_EMAILS as $email) {
            $approver = $approversByEmail->get(strtolower($email));

            if ($approver && (bool) ($approver->is_available_for_approval ?? true)) {
                return $approver->id;
            }
        }

        foreach (self::HR_APPROVER_PRIORITY_EMAILS as $email) {
            $approver = $approversByEmail->get(strtolower($email));

            if ($approver) {
                return $approver->id;
            }
        }

        return null;
    }

    private function canSubmitApproval(ExitPermit $exitPermit, $user): bool
    {
        $roleCode = $user?->role?->code;

        if (!in_array($roleCode, ['manager', 'md', 'hr_manager'], true)) {
            return false;
        }

        if (
            $user?->isWidaMustikaSari()
            && $exitPermit->status === 'pending'
            && $exitPermit->manager_approved_at
            && $exitPermit->md_approved_at
            && !$exitPermit->hr_verified_at
        ) {
            return true;
        }

        if ($roleCode === 'manager') {
            return $exitPermit->status === 'pending'
                && !$exitPermit->manager_approved_at
                && $this->canUserApproveManagerStage($exitPermit, $user);
        }

        if ($roleCode === 'md') {
            return $exitPermit->status === 'pending'
                && (bool) $exitPermit->manager_approved_at
                && !$exitPermit->md_approved_at;
        }

        if ($roleCode === 'hr_manager') {
            return $exitPermit->status === 'pending'
                && (bool) $exitPermit->manager_approved_at
                && (bool) $exitPermit->md_approved_at
                && !$exitPermit->hr_verified_at
                && (!$exitPermit->hr_approver_id || $exitPermit->hr_approver_id === $user?->id);
        }

        return false;
    }

    private function exitPermitPayload(ExitPermit $exitPermit): array
    {
        $exitPermit->loadMissing([
            'user:id,name,nik',
            'requestors:id,exit_permit_id,name,department',
        ]);

        return [
            'id' => $exitPermit->id,
            'status' => $exitPermit->status,
            'permit_date' => $exitPermit->permit_date ? (string) $exitPermit->permit_date : null,
            'destination' => $exitPermit->destination,
            'exit_type' => $exitPermit->exit_type,
            'manager_approved_at' => optional($exitPermit->manager_approved_at)->toDateTimeString(),
            'md_approved_at' => optional($exitPermit->md_approved_at)->toDateTimeString(),
            'hr_verified_at' => optional($exitPermit->hr_verified_at)->toDateTimeString(),
            'user' => [
                'id' => $exitPermit->user?->id,
                'name' => $exitPermit->user?->name,
                'nik' => $exitPermit->user?->nik,
            ],
            'requestors' => $exitPermit->requestors
                ->map(fn($requestor) => [
                    'id' => $requestor->id,
                    'name' => $requestor->name,
                    'department' => $requestor->department,
                ])
                ->values()
                ->all(),
        ];
    }

    private function canViewExitPermit(ExitPermit $exitPermit, $user): bool
    {
        $roleCode = $user?->role?->code;

        if (!in_array($roleCode, ['manager', 'md', 'hr_manager'], true)) {
            return false;
        }

        if ($roleCode === 'manager') {
            return $this->canUserApproveManagerStage($exitPermit, $user);
        }

        return true;
    }

    private function canUserApproveManagerStage(ExitPermit $exitPermit, $user): bool
    {
        $scope = $this->managerApprovalScopeForUser($user);

        if (!$scope) {
            return true;
        }

        return $this->exitPermitMatchesManagerScope($exitPermit, $scope);
    }

    private function exitPermitMatchesManagerScope(ExitPermit $exitPermit, array $scope): bool
    {
        if (!$exitPermit->relationLoaded('user') || !$exitPermit->user?->nik) {
            $exitPermit->load('user:id,name,nik');
        }

        if (!$exitPermit->relationLoaded('requestors') || $exitPermit->requestors->contains(fn($requestor) => $requestor->department === null)) {
            $exitPermit->load('requestors:id,exit_permit_id,name,department');
        }

        $creatorTokens = array_values(array_filter(array_map(
            fn($value) => $this->normalizeApprovalToken($value),
            (array) ($scope['creators'] ?? []),
        )));
        $departmentTokens = array_values(array_filter(array_map(
            fn($value) => $this->normalizeApprovalToken($value),
            (array) ($scope['departments'] ?? []),
        )));

        $owner = $exitPermit->user;
        $ownerTokens = array_values(array_filter([
            $this->normalizeApprovalToken((string) ($owner?->name ?? '')),
            $this->normalizeApprovalToken((string) ($owner?->nik ?? '')),
        ]));

        if ($creatorTokens === [] || count(array_intersect($ownerTokens, $creatorTokens)) === 0) {
            return false;
        }

        if ($departmentTokens === []) {
            return true;
        }

        $requestors = $exitPermit->requestors;

        if ($requestors->isEmpty()) {
            return false;
        }

        foreach ($requestors as $requestor) {
            $departmentToken = $this->normalizeApprovalToken((string) ($requestor->department ?? ''));

            if ($departmentToken === '' || !in_array($departmentToken, $departmentTokens, true)) {
                return false;
            }
        }

        return true;
    }

    private function managerApprovalScopeForUser($user): ?array
    {
        $approverKey = $this->normalizeApprovalToken((string) ($user?->nik ?? ''));
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

    private function normalizeApprovalToken(?string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)) ?? '');
    }
}
