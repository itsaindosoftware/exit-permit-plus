<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExitPermit;
use App\Models\Reimbursement;
use App\Models\User;
use App\Notifications\ReimbursementApprovalRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReimbursementController extends Controller
{
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
            'departments' => ['Maintenance Dies'],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->isManagerOrMd($user)) {
            abort(403);
        }

        $filters = $this->listFiltersFromRequest($request);
        $query = Reimbursement::query()
            ->with(['user:id,name,email', 'exitPermit:id,permit_date,destination,cost_center_id'])
            ->latest();

        if ($user?->role?->code === 'manager') {
            $query->where('status', Reimbursement::STATUS_PENDING_MANAGER);
            $this->applyManagerApprovalScope($query, $user);
        } elseif ($user?->role?->code === 'md') {
            $query->where('status', Reimbursement::STATUS_PENDING_MD);
        }

        $this->applyListFilters($query, $filters);

        $paginated = $query->paginate(10)->withQueryString();
        $paginated->getCollection()->transform(fn(Reimbursement $reimbursement) => $this->transformReimbursementListItem($reimbursement, $user));

        return response()->json($paginated);
    }

    public function show(Reimbursement $reimbursement): JsonResponse
    {
        $user = request()->user();

        if (!$this->canViewReimbursementDetail($reimbursement, $user)) {
            abort(403);
        }

        $reimbursement->load([
            'user:id,name,email,nik',
            'exitPermit:id,permit_date,destination,cost_center_id',
            'exitPermit.costCenter:id,name',
            'exitPermit.requestors:id,exit_permit_id,name,department',
            'managerApprover:id,name',
            'mdApprover:id,name',
            'ratnaSubmitter:id,name',
            'accountingProcessor:id,name',
            'documents',
        ]);

        return response()->json([
            'reimbursement' => $this->transformReimbursementDetail($reimbursement, $user),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'exit_permit_id' => ['nullable', 'integer', 'exists:exit_permits,id'],
            'request_date' => ['required', 'date'],
            'paid_to' => ['required', 'string', 'max:255'],
            'amount_order_meal' => ['required', 'integer', 'min:0'],
            'amount_fuel' => ['nullable', 'integer', 'min:0'],
            'amount_toll' => ['nullable', 'integer', 'min:0'],
            'amount_in_words' => ['required', 'string', 'max:255'],
            'expense_type' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string'],
            'ref_document' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $exitPermit = null;

        if (!empty($validated['exit_permit_id'])) {
            $exitPermit = ExitPermit::query()->findOrFail((int) $validated['exit_permit_id']);
            $this->ensureEligibleForReimbursementPath($exitPermit, $user->id);

            $alreadyFinished = Reimbursement::query()
                ->where('exit_permit_id', $exitPermit->id)
                ->where('status', Reimbursement::STATUS_FINISHED)
                ->exists();

            if ($alreadyFinished) {
                throw ValidationException::withMessages([
                    'exit_permit_id' => 'Reimbursement for this Exit Permit already has Paid by Accounting status.',
                ]);
            }
        }

        $amountOrderMeal = (int) $validated['amount_order_meal'];
        $amountFuel = (int) ($validated['amount_fuel'] ?? 0);
        $amountToll = (int) ($validated['amount_toll'] ?? 0);
        $totalAmount = $amountOrderMeal + $amountFuel + $amountToll;

        $reimbursement = Reimbursement::create([
            'exit_permit_id' => $exitPermit?->id,
            'user_id' => $user->id,
            'request_date' => $validated['request_date'],
            'paid_to' => $validated['paid_to'],
            'amount' => $totalAmount,
            'amount_order_meal' => $amountOrderMeal,
            'amount_fuel' => $amountFuel,
            'amount_toll' => $amountToll,
            'amount_in_words' => $validated['amount_in_words'],
            'expense_type' => $validated['expense_type'],
            'purpose' => $validated['purpose'],
            'ref_document' => $validated['ref_document'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => Reimbursement::STATUS_PENDING_MANAGER,
        ]);

        $managers = User::query()
            ->whereHas('role', fn($q) => $q->where('code', 'manager'))
            ->where('is_available_for_approval', true)
            ->get();

        foreach ($managers as $manager) {
            if (!$this->canUserApproveManagerStage($reimbursement, $manager)) {
                continue;
            }

            $this->notifyReimbursementApproverOnce($manager, $reimbursement, 'manager');
        }

        return response()->json([
            'message' => 'Reimbursement has been submitted successfully.',
            'data' => [
                'id' => $reimbursement->id,
                'status' => $reimbursement->status,
            ],
        ], 201);
    }

    private function listFiltersFromRequest(Request $request): array
    {
        $amountRaw = preg_replace('/[^0-9]/', '', (string) $request->query('amount', ''));

        return [
            'employee' => trim((string) $request->query('employee', '')),
            'exit_permit' => trim((string) $request->query('exit_permit', '')),
            'date' => trim((string) $request->query('date', '')),
            'amount' => $amountRaw !== '' ? $amountRaw : '',
            'status' => trim((string) $request->query('status', '')),
            'stage' => trim((string) $request->query('stage', '')),
        ];
    }

    private function applyListFilters($query, array $filters): void
    {
        $employee = (string) ($filters['employee'] ?? '');
        $exitPermit = (string) ($filters['exit_permit'] ?? '');
        $date = (string) ($filters['date'] ?? '');
        $amount = (string) ($filters['amount'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $stage = (string) ($filters['stage'] ?? '');

        if ($employee !== '') {
            $query->whereHas('user', function ($subQuery) use ($employee) {
                $subQuery->where('name', 'like', '%' . $employee . '%');
            });
        }

        if ($exitPermit !== '') {
            $query->where(function ($subQuery) use ($exitPermit) {
                $subQuery->where('exit_permit_id', 'like', '%' . $exitPermit . '%')
                    ->orWhereHas('exitPermit', function ($permitQuery) use ($exitPermit) {
                        $permitQuery->where('destination', 'like', '%' . $exitPermit . '%')
                            ->orWhere('permit_date', 'like', '%' . $exitPermit . '%');
                    });
            });
        }

        if ($date !== '') {
            $query->whereDate('request_date', $date);
        }

        if ($amount !== '') {
            $query->where('amount', (int) $amount);
        }

        if ($status !== '' && in_array($status, Reimbursement::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($stage !== '') {
            $stageStatuses = $this->stageStatuses($stage);

            if (count($stageStatuses) > 0) {
                $query->whereIn('status', $stageStatuses);
            }
        }
    }

    private function stageStatuses(string $stage): array
    {
        return match ($stage) {
            'manager' => [Reimbursement::STATUS_PENDING_MANAGER],
            'md' => [Reimbursement::STATUS_PENDING_MD],
            'ratna' => [Reimbursement::STATUS_PENDING_RATNA],
            'accounting' => [Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING],
            'finished' => [Reimbursement::STATUS_FINISHED],
            'rejected' => [Reimbursement::STATUS_REJECTED],
            default => [],
        };
    }

    private function transformReimbursementListItem(Reimbursement $reimbursement, $user): array
    {
        return [
            'id' => $reimbursement->id,
            'employee_name' => $reimbursement->user?->name,
            'exit_permit_id' => $reimbursement->exit_permit_id,
            'exit_permit_label' => $this->exitPermitLabel($reimbursement->exitPermit),
            'request_date' => $reimbursement->request_date ? (string) $reimbursement->request_date : null,
            'paid_to' => $reimbursement->paid_to,
            'amount' => $reimbursement->amount,
            'amount_order_meal' => (int) ($reimbursement->amount_order_meal ?? 0),
            'amount_fuel' => (int) ($reimbursement->amount_fuel ?? 0),
            'amount_toll' => (int) ($reimbursement->amount_toll ?? 0),
            'amount_in_words' => $reimbursement->amount_in_words,
            'expense_type' => $reimbursement->expense_type,
            'purpose' => $reimbursement->purpose,
            'ref_document' => $reimbursement->ref_document,
            'description' => $reimbursement->description,
            'status' => $reimbursement->status,
            'approval_stage' => $this->approvalStageLabel($reimbursement),
            'can_approve_manager' => $this->canApproveManager($reimbursement, $user),
            'can_approve_md' => $this->canApproveMd($reimbursement, $user),
        ];
    }

    private function transformReimbursementDetail(Reimbursement $reimbursement, $user): array
    {
        return [
            'id' => $reimbursement->id,
            'employee_name' => $reimbursement->user?->name,
            'employee_email' => $reimbursement->user?->email,
            'exit_permit_id' => $reimbursement->exit_permit_id,
            'exit_permit_label' => $this->exitPermitLabel($reimbursement->exitPermit),
            'request_date' => $reimbursement->request_date ? (string) $reimbursement->request_date : null,
            'paid_to' => $reimbursement->paid_to,
            'amount_order_meal' => (int) ($reimbursement->amount_order_meal ?? 0),
            'amount_fuel' => (int) ($reimbursement->amount_fuel ?? 0),
            'amount_toll' => (int) ($reimbursement->amount_toll ?? 0),
            'amount' => $reimbursement->amount,
            'amount_in_words' => $reimbursement->amount_in_words,
            'expense_type' => $reimbursement->expense_type,
            'purpose' => $reimbursement->purpose,
            'ref_document' => $reimbursement->ref_document,
            'description' => $reimbursement->description,
            'status' => $reimbursement->status,
            'approval_stage' => $this->approvalStageLabel($reimbursement),
            'manager_approved_by_name' => $reimbursement->managerApprover?->name,
            'manager_approved_at' => optional($reimbursement->manager_approved_at)->toDateTimeString(),
            'md_approved_by_name' => $reimbursement->mdApprover?->name,
            'md_approved_at' => optional($reimbursement->md_approved_at)->toDateTimeString(),
            'ratna_submitted_by_name' => $reimbursement->ratnaSubmitter?->name,
            'ratna_submitted_at' => optional($reimbursement->ratna_submitted_at)->toDateTimeString(),
            'accounting_processed_by_name' => $reimbursement->accountingProcessor?->name,
            'accounting_processed_at' => optional($reimbursement->accounting_processed_at)->toDateTimeString(),
            'documents' => $reimbursement->documents
                ->map(fn($document) => [
                    'id' => $document->id,
                    'ref_document' => $document->ref_document,
                    'attachment_original_name' => $document->attachment_original_name,
                    'attachment_url' => $document->attachment_path
                        ? route('reimbursement-documents.attachment', $document)
                        : null,
                ])
                ->values()
                ->all(),
            'can_approve_manager' => $this->canApproveManager($reimbursement, $user),
            'can_approve_md' => $this->canApproveMd($reimbursement, $user),
        ];
    }

    private function canViewReimbursementDetail(Reimbursement $reimbursement, $user): bool
    {
        return ($this->canApproveManager($reimbursement, $user) || $this->canApproveMd($reimbursement, $user))
            && $this->isManagerOrMd($user);
    }

    private function ensureEligibleForReimbursementPath(ExitPermit $exitPermit, int $userId): void
    {
        if ((int) $exitPermit->user_id !== $userId) {
            throw ValidationException::withMessages([
                'exit_permit_id' => 'Exit Permit is not owned by the currently logged in user.',
            ]);
        }

        $isEligible = $exitPermit->status === 'approved'
            && (bool) $exitPermit->attendance_checked_at
            && (bool) $exitPermit->has_valid_checkin;

        if ($isEligible) {
            return;
        }

        throw ValidationException::withMessages([
            'exit_permit_id' => 'Exit Permit has not been Checked By HR: Sisca yet.',
        ]);
    }

    private function isManagerOrMd($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md'], true);
    }

    private function canApproveManager(Reimbursement $reimbursement, $user): bool
    {
        return $user?->role?->code === 'manager'
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MANAGER
            && $this->canUserApproveManagerStage($reimbursement, $user);
    }

    private function canApproveMd(Reimbursement $reimbursement, $user): bool
    {
        return $user?->role?->code === 'md'
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MD;
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

    private function applyManagerApprovalScope($query, $user): void
    {
        $scope = $this->reimbursementApprovalScopeForUser($user);

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
                $userQuery->whereIn(DB::raw('LOWER(TRIM(name))'), $creatorNames);
            });
        }

        if ($departments !== []) {
            $query->whereHas('exitPermit', function ($exitPermitQuery) use ($departments) {
                $exitPermitQuery->whereDoesntHave('requestors', function ($requestorQuery) use ($departments) {
                    $requestorQuery->whereNotIn(DB::raw('LOWER(TRIM(COALESCE(department, "")))'), $departments);
                });
            });
        }
    }

    private function approvalStageLabel(Reimbursement $reimbursement): string
    {
        return match ($reimbursement->status) {
            Reimbursement::STATUS_PENDING_MANAGER => 'Waiting for Manager Approval',
            Reimbursement::STATUS_PENDING_MD => 'Waiting for MD Approval',
            Reimbursement::STATUS_PENDING_RATNA => 'Waiting for Ratna Check & Submit to Accounting',
            Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING => 'Waiting for Accounting Processing',
            Reimbursement::STATUS_FINISHED => 'Paid by Accounting',
            Reimbursement::STATUS_REJECTED => 'Rejected',
            default => 'Pending',
        };
    }

    private function exitPermitLabel(?\Illuminate\Database\Eloquent\Model $exitPermit): string
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
