<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\Reimbursement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReimbursementController extends Controller
{
    private const RATNA_EMAIL = 'ratna@example.com';

    public function index(): Response
    {
        $user = request()->user();
        $isUserRole = $user?->role?->code === 'user';
        $eligibleExitPermits = $isUserRole ? $this->eligibleExitPermitsForUser($user->id) : [];
        $query = Reimbursement::query()
            ->with(['user:id,name', 'exitPermit:id,permit_date,destination'])
            ->latest();

        if (!$this->canViewAllData($user)) {
            $query->where('user_id', $user->id);
        }

        return Inertia::render('Reimbursements/Index', [
            'viewerRole' => $user?->role?->code,
            'canCreate' => $isUserRole && count($eligibleExitPermits) > 0,
            'eligibleExitPermits' => $eligibleExitPermits,
            'reimbursements' => $query
                ->paginate(10)
                ->through(fn(Reimbursement $reimbursement) => [
                    'id' => $reimbursement->id,
                    'employee_name' => $reimbursement->user?->name,
                    'exit_permit_id' => $reimbursement->exit_permit_id,
                    'exit_permit_label' => $this->exitPermitLabel($reimbursement->exitPermit),
                    'request_date' => $reimbursement->request_date ? (string) $reimbursement->request_date : null,
                    'amount' => $reimbursement->amount,
                    'description' => $reimbursement->description,
                    'status' => $reimbursement->status,
                    'approval_stage' => $this->approvalStageLabel($reimbursement),
                    'can_update_request' => $this->canOwnerUpdate($reimbursement, $user),
                    'can_take_action' => $this->canTakeApprovalAction($reimbursement, $user),
                ]),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $user = request()->user();

        if ($user?->role?->code !== 'user') {
            abort(403);
        }

        $eligibleExitPermits = $this->eligibleExitPermitsForUser($user->id);

        if (count($eligibleExitPermits) === 0) {
            return redirect()->route('reimbursements.index')
                ->with('warning', 'Form reimbursement hanya tersedia setelah Exit Permit diverifikasi Sisca.');
        }

        return Inertia::render('Reimbursements/Create', [
            'eligibleExitPermits' => $eligibleExitPermits,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->role?->code !== 'user') {
            abort(403);
        }

        $validated = $request->validate([
            'exit_permit_id' => ['required', 'integer', 'exists:exit_permits,id'],
            'request_date' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $exitPermit = ExitPermit::query()->findOrFail($validated['exit_permit_id']);
        $this->ensureEligibleForReimbursementPath($exitPermit, $user->id);

        $alreadyExists = Reimbursement::query()
            ->where('exit_permit_id', $exitPermit->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'exit_permit_id' => 'Form reimbursement untuk Exit Permit ini sudah pernah diajukan.',
            ]);
        }

        Reimbursement::create([
            'exit_permit_id' => $exitPermit->id,
            'user_id' => $user->id,
            'request_date' => $validated['request_date'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'status' => Reimbursement::STATUS_PENDING_MANAGER,
        ]);

        return redirect()->route('reimbursements.index')->with('success', 'Form reimbursement berhasil diajukan.');
    }

    public function edit(Reimbursement $reimbursement): Response
    {
        $this->authorizeUser($reimbursement);

        $user = request()->user();

        return Inertia::render('Reimbursements/Edit', [
            'viewerRole' => $user?->role?->code,
            'reimbursement' => [
                'id' => $reimbursement->id,
                'exit_permit_id' => $reimbursement->exit_permit_id,
                'exit_permit_label' => $this->exitPermitLabel($reimbursement->exitPermit),
                'request_date' => $reimbursement->request_date ? (string) $reimbursement->request_date : null,
                'amount' => $reimbursement->amount,
                'description' => $reimbursement->description,
                'status' => $reimbursement->status,
                'approval_stage' => $this->approvalStageLabel($reimbursement),
            ],
            'canUpdateRequest' => $this->canOwnerUpdate($reimbursement, $user),
            'canApproveManager' => $this->canApproveManager($reimbursement, $user),
            'canApproveMd' => $this->canApproveMd($reimbursement, $user),
            'canSubmitRatna' => $this->canSubmitRatna($reimbursement, $user),
            'canFinishAccounting' => $this->canFinishAccounting($reimbursement, $user),
        ]);
    }

    public function update(Request $request, Reimbursement $reimbursement): RedirectResponse
    {
        $this->authorizeUser($reimbursement);

        $user = $request->user();
        $isOwner = $reimbursement->user_id === $user?->id;

        if ($isOwner) {
            if (!$this->canOwnerUpdate($reimbursement, $user)) {
                throw ValidationException::withMessages([
                    'status' => 'Form reimbursement yang sudah diproses tidak dapat diubah oleh pemohon.',
                ]);
            }

            $validated = $request->validate([
                'request_date' => ['required', 'date'],
                'amount' => ['required', 'integer', 'min:0'],
                'description' => ['nullable', 'string'],
            ]);

            $reimbursement->fill($validated);
            $reimbursement->save();

            return redirect()->route('reimbursements.index')->with('success', 'Form reimbursement berhasil diperbarui.');
        }

        if ($this->canApproveManager($reimbursement, $user)) {
            $status = $this->validateStatus($request, ['approved', 'rejected']);
            $reimbursement->manager_approved_by = $user?->id;
            $reimbursement->manager_approved_at = now();
            $reimbursement->status = $status === 'approved'
                ? Reimbursement::STATUS_PENDING_MD
                : Reimbursement::STATUS_REJECTED;
            $reimbursement->save();

            return redirect()->route('reimbursements.index')->with('success', 'Approval manager berhasil diproses.');
        }

        if ($this->canApproveMd($reimbursement, $user)) {
            $status = $this->validateStatus($request, ['approved', 'rejected']);
            $reimbursement->md_approved_by = $user?->id;
            $reimbursement->md_approved_at = now();
            $reimbursement->status = $status === 'approved'
                ? Reimbursement::STATUS_PENDING_RATNA
                : Reimbursement::STATUS_REJECTED;
            $reimbursement->save();

            return redirect()->route('reimbursements.index')->with('success', 'Approval MD berhasil diproses.');
        }

        if ($this->canSubmitRatna($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING]);
            $reimbursement->ratna_submitted_by = $user?->id;
            $reimbursement->ratna_submitted_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            return redirect()->route('reimbursements.index')->with('success', 'Reimbursement berhasil disubmit ke accounting.');
        }

        if ($this->canFinishAccounting($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_FINISHED]);
            $reimbursement->accounting_processed_by = $user?->id;
            $reimbursement->accounting_processed_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            return redirect()->route('reimbursements.index')->with('success', 'Reimbursement telah diselesaikan oleh accounting.');
        }

        throw ValidationException::withMessages([
            'status' => 'Anda tidak memiliki akses untuk memproses reimbursement ini.',
        ]);
    }

    private function eligibleExitPermitsForUser(int $userId): array
    {
        return ExitPermit::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereNotNull('md_approved_at')
            ->whereNotNull('attendance_checked_at')
            ->whereDoesntHave('reimbursements')
            ->latest('permit_date')
            ->get(['id', 'permit_date', 'destination'])
            ->map(fn(ExitPermit $exitPermit) => [
                'id' => $exitPermit->id,
                'label' => $this->exitPermitLabel($exitPermit),
            ])
            ->values()
            ->all();
    }

    private function ensureEligibleForReimbursementPath(ExitPermit $exitPermit, int $userId): void
    {
        if ((int) $exitPermit->user_id !== $userId) {
            throw ValidationException::withMessages([
                'exit_permit_id' => 'Exit Permit tidak dimiliki oleh user yang sedang login.',
            ]);
        }

        $isEligible = $exitPermit->status === 'approved'
            && (bool) $exitPermit->md_approved_at
            && (bool) $exitPermit->attendance_checked_at;

        if ($isEligible) {
            return;
        }

        throw ValidationException::withMessages([
            'exit_permit_id' => 'Exit Permit belum memenuhi syarat reimbursement (setelah verifikasi Sisca).',
        ]);
    }

    private function validateStatus(Request $request, array $allowedStatuses): string
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
        ]);

        return (string) $validated['status'];
    }

    private function authorizeUser(Reimbursement $reimbursement): void
    {
        $user = request()->user();

        if ($reimbursement->user_id === $user?->id) {
            return;
        }

        if ($this->canViewAllData($user)) {
            return;
        }

        abort(403);
    }

    private function canViewAllData($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md', 'hr', 'accounting', 'admin'], true);
    }

    private function canOwnerUpdate(Reimbursement $reimbursement, $user): bool
    {
        return $reimbursement->user_id === $user?->id
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MANAGER;
    }

    private function canApproveManager(Reimbursement $reimbursement, $user): bool
    {
        return $user?->role?->code === 'manager'
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MANAGER;
    }

    private function canApproveMd(Reimbursement $reimbursement, $user): bool
    {
        return $user?->role?->code === 'md'
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MD;
    }

    private function canSubmitRatna(Reimbursement $reimbursement, $user): bool
    {
        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::RATNA_EMAIL
            && $reimbursement->status === Reimbursement::STATUS_PENDING_RATNA;
    }

    private function canFinishAccounting(Reimbursement $reimbursement, $user): bool
    {
        return $user?->role?->code === 'accounting'
            && $reimbursement->status === Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING;
    }

    private function canTakeApprovalAction(Reimbursement $reimbursement, $user): bool
    {
        return $this->canApproveManager($reimbursement, $user)
            || $this->canApproveMd($reimbursement, $user)
            || $this->canSubmitRatna($reimbursement, $user)
            || $this->canFinishAccounting($reimbursement, $user);
    }

    private function approvalStageLabel(Reimbursement $reimbursement): string
    {
        return match ($reimbursement->status) {
            Reimbursement::STATUS_PENDING_MANAGER => 'Menunggu Approval Manager',
            Reimbursement::STATUS_PENDING_MD => 'Menunggu Approval MD',
            Reimbursement::STATUS_PENDING_RATNA => 'Menunggu Ratna Submit Accounting',
            Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING => 'Menunggu Proses Accounting',
            Reimbursement::STATUS_FINISHED => 'Finished',
            Reimbursement::STATUS_REJECTED => 'Rejected',
            default => 'Pending',
        };
    }

    private function exitPermitLabel(?ExitPermit $exitPermit): string
    {
        if (!$exitPermit) {
            return '-';
        }

        return sprintf(
            '#%d | %s | %s',
            $exitPermit->id,
            $exitPermit->permit_date ? (string) $exitPermit->permit_date : '-',
            $exitPermit->destination,
        );
    }
}
