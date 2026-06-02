<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use App\Models\User;
use App\Notifications\ReimbursementApprovalRequested;
use App\Notifications\ReimbursementStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReimbursementApprovalController extends Controller
{
    private const RATNA_EMAIL = 'hrga-01@example.com';

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

    public function submit(Request $request, Reimbursement $reimbursement): JsonResponse
    {
        $user = $request->user();

        if (!$this->isManagerOrMd($user)) {
            abort(403);
        }

        if ($this->canApproveManager($reimbursement, $user)) {
            $status = $this->validateStatus($request, ['approved', 'rejected']);
            $reimbursement->manager_approved_by = $user?->id;
            $reimbursement->manager_approved_at = now();
            $reimbursement->status = $status === 'approved'
                ? Reimbursement::STATUS_PENDING_MD
                : Reimbursement::STATUS_REJECTED;
            $reimbursement->save();

            if ($status === 'approved') {
                $this->notifyReimbursementOwner($reimbursement, 'manager_approved');

                $mds = User::query()
                    ->whereHas('role', fn($q) => $q->where('code', 'md'))
                    ->where('is_available_for_approval', true)
                    ->get();

                foreach ($mds as $md) {
                    $this->notifyReimbursementApproverOnce($md, $reimbursement, 'md');
                }
            }

            return response()->json([
                'message' => 'Manager approval has been processed.',
                'data' => $this->approvalSummary($reimbursement),
            ]);
        }

        if ($this->canApproveMd($reimbursement, $user)) {
            $status = $this->validateStatus($request, ['approved', 'rejected']);
            $reimbursement->md_approved_by = $user?->id;
            $reimbursement->md_approved_at = now();
            $reimbursement->status = $status === 'approved'
                ? Reimbursement::STATUS_PENDING_RATNA
                : Reimbursement::STATUS_REJECTED;
            $reimbursement->save();

            if ($status === 'approved') {
                $this->notifyReimbursementOwner($reimbursement, 'md_approved');
            }

            return response()->json([
                'message' => 'MD approval has been processed.',
                'data' => $this->approvalSummary($reimbursement),
            ]);
        }

        if ($this->canSubmitRatna($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING]);
            $reimbursement->ratna_submitted_by = $user?->id;
            $reimbursement->ratna_submitted_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            $this->notifyReimbursementOwner($reimbursement, 'submitted_to_accounting');

            return response()->json([
                'message' => 'Reimbursement has been submitted to accounting.',
                'data' => $this->approvalSummary($reimbursement),
            ]);
        }

        if ($this->canFinishAccounting($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_FINISHED]);
            $reimbursement->accounting_processed_by = $user?->id;
            $reimbursement->accounting_processed_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            return response()->json([
                'message' => 'Reimbursement has been completed by accounting.',
                'data' => $this->approvalSummary($reimbursement),
            ]);
        }

        throw ValidationException::withMessages([
            'status' => 'You do not have access to process this reimbursement.',
        ]);
    }

    private function approvalSummary(Reimbursement $reimbursement): array
    {
        return [
            'id' => $reimbursement->id,
            'status' => $reimbursement->status,
            'manager_approved_at' => optional($reimbursement->manager_approved_at)->toDateTimeString(),
            'md_approved_at' => optional($reimbursement->md_approved_at)->toDateTimeString(),
            'ratna_submitted_at' => optional($reimbursement->ratna_submitted_at)->toDateTimeString(),
            'accounting_processed_at' => optional($reimbursement->accounting_processed_at)->toDateTimeString(),
        ];
    }

    private function validateStatus(Request $request, array $allowedStatuses): string
    {
        if (!$request->filled('status')) {
            return (string) ($allowedStatuses[0] ?? '');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
        ]);

        return (string) $validated['status'];
    }

    private function notifyReimbursementOwner(Reimbursement $reimbursement, string $stage): void
    {
        $reimbursement->loadMissing('user:id,name,email');
        $owner = $reimbursement->user;

        if (!$owner || !filled($owner->email)) {
            return;
        }

        $owner->notify(new ReimbursementStatusUpdated($reimbursement, $stage));
    }

    private function notifyReimbursementApproverOnce(User $approver, Reimbursement $reimbursement, string $stage): void
    {
        $alreadyNotified = $approver->notifications()
            ->where('type', ReimbursementApprovalRequested::class)
            ->where('data->reimbursement_id', $reimbursement->id)
            ->where('data->stage', $stage)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $approver->notify(new ReimbursementApprovalRequested($reimbursement, $stage));
    }

    private function canApproveManager(Reimbursement $reimbursement, $user): bool
    {
        return in_array($user?->role?->code, ['manager', 'hr_manager', 'admin'], true)
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MANAGER
            && $this->canUserApproveManagerStage($reimbursement, $user);
    }

    private function isManagerOrMd($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md'], true);
    }

    private function canApproveMd(Reimbursement $reimbursement, $user): bool
    {
        return in_array($user?->role?->code, ['md', 'admin'], true)
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MD;
    }

    private function canSubmitRatna(Reimbursement $reimbursement, $user): bool
    {
        if ($user?->role?->code === 'admin') {
            return $reimbursement->status === Reimbursement::STATUS_PENDING_RATNA;
        }

        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::RATNA_EMAIL
            && $reimbursement->status === Reimbursement::STATUS_PENDING_RATNA;
    }

    private function canFinishAccounting(Reimbursement $reimbursement, $user): bool
    {
        return in_array($user?->role?->code, ['accounting', 'admin'], true)
            && $reimbursement->status === Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING;
    }

    public function reject(Request $request, Reimbursement $reimbursement): JsonResponse
    {
        $user = $request->user();

        if (!$this->isManagerOrMd($user)) {
            abort(403);
        }

        if ($this->canApproveManager($reimbursement, $user)) {
            $reimbursement->manager_approved_by = $user?->id;
            $reimbursement->manager_approved_at = now();
            $reimbursement->status = Reimbursement::STATUS_REJECTED;
            $reimbursement->save();
            $this->notifyReimbursementOwner($reimbursement, 'rejected');

            return response()->json([
                'message' => 'Manager rejection has been processed.',
                'data' => $this->approvalSummary($reimbursement),
            ]);
        }

        if ($this->canApproveMd($reimbursement, $user)) {
            $reimbursement->md_approved_by = $user?->id;
            $reimbursement->md_approved_at = now();
            $reimbursement->status = Reimbursement::STATUS_REJECTED;
            $reimbursement->save();
            $this->notifyReimbursementOwner($reimbursement, 'rejected');

            return response()->json([
                'message' => 'MD rejection has been processed.',
                'data' => $this->approvalSummary($reimbursement),
            ]);
        }

        throw ValidationException::withMessages([
            'status' => 'You do not have access to reject this reimbursement.',
        ]);
    }

    private function canUserApproveManagerStage(Reimbursement $reimbursement, $user): bool
    {
        $scope = $this->reimbursementApprovalScopeForUser($user);

        if (!$scope) {
            return true;
        }

        return $this->reimbursementMatchesManagerScope($reimbursement, $scope);
    }

    private function reimbursementApprovalScopeForUser($user): ?array
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

    private function reimbursementMatchesManagerScope(Reimbursement $reimbursement, array $scope): bool
    {
        $reimbursement->loadMissing('user:id,name,nik', 'exitPermit.requestors:id,exit_permit_id,name,department');

        $creatorTokens = array_values(array_filter(array_map(
            fn($value) => $this->normalizeApprovalToken($value),
            (array) ($scope['creators'] ?? []),
        )));
        $departmentTokens = array_values(array_filter(array_map(
            fn($value) => $this->normalizeApprovalToken($value),
            (array) ($scope['departments'] ?? []),
        )));

        $owner = $reimbursement->user;
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

        $requestors = $reimbursement->exitPermit?->requestors;

        if (!$requestors || $requestors->isEmpty()) {
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

    private function normalizeApprovalToken(?string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)) ?? '');
    }
}
