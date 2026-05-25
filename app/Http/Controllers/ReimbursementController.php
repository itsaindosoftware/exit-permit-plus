<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\Reimbursement;
use App\Models\ReimbursementDocument;
use App\Models\User;
use App\Notifications\ReimbursementApprovalRequested;
use App\Notifications\ReimbursementStatusUpdated;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReimbursementController extends Controller
{
    private const RATNA_EMAIL = 'hrga-01@example.com';

    private const FORM_SOURCE_INTERNAL = 'internal';

    private const FORM_SOURCE_EXIT_PERMIT = 'exit_permit';

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

    public function attachment(Reimbursement $reimbursement): StreamedResponse
    {
        $this->authorizeUser($reimbursement);

        if (!$reimbursement->attachment_path || !Storage::exists($reimbursement->attachment_path)) {
            abort(404);
        }

        return Storage::response(
            $reimbursement->attachment_path,
            $reimbursement->attachment_original_name ?: basename($reimbursement->attachment_path),
            [
                'Content-Disposition' => 'inline',
            ],
        );
    }

    public function documentAttachment(ReimbursementDocument $document): StreamedResponse
    {
        $reimbursement = $document->reimbursement;

        if (!$reimbursement) {
            abort(404);
        }

        $this->authorizeUser($reimbursement);

        if (!$document->attachment_path || !Storage::exists($document->attachment_path)) {
            abort(404);
        }

        return Storage::response(
            $document->attachment_path,
            $document->attachment_original_name ?: basename($document->attachment_path),
            [
                'Content-Disposition' => 'inline',
            ],
        );
    }

    public function print(Reimbursement $reimbursement)
    {
        $this->authorizeUser($reimbursement);

        $reimbursement->load([
            'user:id,name',
            'managerApprover:id,name',
            'mdApprover:id,name',
            'ratnaSubmitter:id,name',
            'accountingProcessor:id,name',
        ]);

        return Pdf::loadView('pdf.reimbursement-internal-receipt', [
            'reimbursement' => $reimbursement,
        ])
            ->setPaper('a4', 'portrait')
            ->stream('reimbursement-' . $reimbursement->id . '.pdf');
    }

    public function index(): Response
    {
        $user = request()->user();
        $filters = $this->listFiltersFromRequest();
        $isRequester = $this->isRequesterRole($user);
        $eligibleExitPermits = $isRequester ? $this->eligibleExitPermitsForUser($user->id) : [];
        $query = Reimbursement::query()
            ->with(['user:id,name', 'exitPermit:id,permit_date,destination'])
            ->latest();

        $query->where('user_id', $user?->id);
        $this->applyListFilters($query, $filters);

        return Inertia::render('Reimbursements/Index', [
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'personal',
            'isRequester' => $isRequester,
            'canCreateInternal' => $isRequester,
            'canCreateFromExitPermit' => $isRequester && count($eligibleExitPermits) > 0,
            'eligibleExitPermits' => $eligibleExitPermits,
            'filters' => $filters,
            'statusOptions' => Reimbursement::STATUSES,
            'stageOptions' => [
                ['value' => 'manager', 'label' => 'Waiting for Manager Approval'],
                ['value' => 'md', 'label' => 'Waiting for MD Approval'],
                ['value' => 'ratna', 'label' => 'Waiting for Ratna Check & Submit to Accounting'],
                ['value' => 'accounting', 'label' => 'Waiting for Accounting Processing'],
                ['value' => 'finished', 'label' => 'Paid by Accounting'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'reimbursements' => $query
                ->paginate(10)
                ->withQueryString()
                ->through(fn(Reimbursement $reimbursement) => $this->transformReimbursementListItem($reimbursement, $user)),
        ]);
    }

    public function approvalIndex(): Response
    {
        $user = request()->user();
        $filters = $this->listFiltersFromRequest();

        if (!$this->canAccessApprovalMenu($user)) {
            abort(403);
        }

        $query = Reimbursement::query()
            ->with(['user:id,name', 'exitPermit:id,permit_date,destination', 'exitPermit.requestors:id,exit_permit_id,name,department'])
            ->latest();

        if ($this->reimbursementApprovalScopeForUser($user)) {
            $query->where('status', Reimbursement::STATUS_PENDING_MANAGER);
            $this->applyManagerApprovalScope($query, $user);
        }

        $this->applyListFilters($query, $filters);

        return Inertia::render('Reimbursements/Index', [
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'approval',
            'isRequester' => false,
            'canCreateInternal' => false,
            'canCreateFromExitPermit' => false,
            'eligibleExitPermits' => [],
            'filters' => $filters,
            'statusOptions' => Reimbursement::STATUSES,
            'stageOptions' => [
                ['value' => 'manager', 'label' => 'Waiting for Manager Approval'],
                ['value' => 'md', 'label' => 'Waiting for MD Approval'],
                ['value' => 'ratna', 'label' => 'Waiting for Ratna Check & Submit to Accounting'],
                ['value' => 'accounting', 'label' => 'Waiting for Accounting Processing'],
                ['value' => 'finished', 'label' => 'Paid by Accounting'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'reimbursements' => $query
                ->paginate(10)
                ->withQueryString()
                ->through(fn(Reimbursement $reimbursement) => $this->transformReimbursementListItem($reimbursement, $user)),
        ]);
    }

    private function listFiltersFromRequest(): array
    {
        $amountRaw = preg_replace('/[^0-9]/', '', (string) request()->query('amount', ''));

        return [
            'employee' => trim((string) request()->query('employee', '')),
            'exit_permit' => trim((string) request()->query('exit_permit', '')),
            'date' => trim((string) request()->query('date', '')),
            'amount' => $amountRaw !== '' ? $amountRaw : '',
            'status' => trim((string) request()->query('status', '')),
            'stage' => trim((string) request()->query('stage', '')),
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

    public function create(): Response|RedirectResponse
    {
        $user = request()->user();

        if (!$this->isRequesterRole($user)) {
            abort(403);
        }

        $source = (string) request()->query('source', self::FORM_SOURCE_INTERNAL);

        if (!in_array($source, [self::FORM_SOURCE_INTERNAL, self::FORM_SOURCE_EXIT_PERMIT], true)) {
            $source = self::FORM_SOURCE_INTERNAL;
        }

        $eligibleExitPermits = $this->eligibleExitPermitsForUser($user->id);

        if ($source === self::FORM_SOURCE_EXIT_PERMIT && count($eligibleExitPermits) === 0) {
            return redirect()->route('reimbursements.index')
                ->with('warning', 'Form From Exit Permit is only available after Exit Permit has been verified by Sisca.');
        }

        return Inertia::render('Reimbursements/Create', [
            'formSource' => $source,
            'eligibleExitPermits' => $eligibleExitPermits,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$this->isRequesterRole($user)) {
            abort(403);
        }

        $source = (string) $request->input('source', self::FORM_SOURCE_INTERNAL);

        if (!in_array($source, [self::FORM_SOURCE_INTERNAL, self::FORM_SOURCE_EXIT_PERMIT], true)) {
            $source = self::FORM_SOURCE_INTERNAL;
        }

        $validated = $request->validate([
            'source' => ['nullable', Rule::in([self::FORM_SOURCE_INTERNAL, self::FORM_SOURCE_EXIT_PERMIT])],
            'exit_permit_id' => [Rule::requiredIf($source === self::FORM_SOURCE_EXIT_PERMIT), 'nullable', 'integer', 'exists:exit_permits,id'],
            'request_date' => ['required', 'date'],
            'paid_to' => ['required', 'string', 'max:255'],
            'amount_order_meal' => ['required', 'integer', 'min:0'],
            'amount_fuel' => ['nullable', 'integer', 'min:0'],
            'amount_toll' => ['nullable', 'integer', 'min:0'],
            'amount_in_words' => ['required', 'string', 'max:255'],
            'expense_type' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string'],
            'ref_document' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array', 'max:30'],
            'documents.*.ref_document' => ['nullable', 'string', 'max:255'],
            'documents.*.attachment_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'description' => ['nullable', 'string'],
            'attachment_file' => [Rule::requiredIf($source === self::FORM_SOURCE_INTERNAL), 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $exitPermit = null;

        if ($source === self::FORM_SOURCE_EXIT_PERMIT) {
            if (empty($validated['exit_permit_id'])) {
                throw ValidationException::withMessages([
                    'exit_permit_id' => 'Exit Permit must be selected for the From Exit Permit form.',
                ]);
            }

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

        $this->syncDocumentsFromRequest($request, $reimbursement);

        $this->syncLegacyDocumentSnapshot($reimbursement);

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $managers */
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

        return redirect()->route('reimbursements.index')->with('success', 'Reimbursement form has been successfully submitted.');
    }

    public function edit(Reimbursement $reimbursement): Response
    {
        $this->authorizeUser($reimbursement);
        $reimbursement->load(['documents', 'exitPermit.costCenter:id,name']);

        $user = request()->user();

        return Inertia::render('Reimbursements/Edit', [
            'viewerRole' => $user?->role?->code,
            'reimbursement' => [
                'id' => $reimbursement->id,
                'exit_permit_id' => $reimbursement->exit_permit_id,
                'exit_permit_label' => $this->exitPermitLabel($reimbursement->exitPermit),
                'request_date' => $this->normalizeDateForInput($reimbursement->request_date),
                'paid_to' => $reimbursement->paid_to,
                'amount' => $reimbursement->amount,
                'amount_order_meal' => (int) ($reimbursement->amount_order_meal ?? $reimbursement->amount ?? 0),
                'amount_fuel' => (int) ($reimbursement->amount_fuel ?? 0),
                'amount_toll' => (int) ($reimbursement->amount_toll ?? 0),
                'amount_in_words' => $reimbursement->amount_in_words,
                'cost_center_name' => $reimbursement->exitPermit?->costCenter?->name,
                'expense_type' => $reimbursement->expense_type,
                'purpose' => $reimbursement->purpose,
                'ref_document' => $reimbursement->ref_document,
                'description' => $reimbursement->description,
                'attachment_original_name' => $reimbursement->attachment_original_name,
                'attachment_url' => $reimbursement->attachment_path
                    ? route('reimbursements.attachment', $reimbursement)
                    : null,
                'status' => $reimbursement->status,
                'approval_stage' => $this->approvalStageLabel($reimbursement),
                'documents' => $reimbursement->documents
                    ->map(fn(ReimbursementDocument $document) => [
                        'id' => $document->id,
                        'ref_document' => $document->ref_document,
                        'attachment_original_name' => $document->attachment_original_name,
                        'attachment_url' => $document->attachment_path
                            ? route('reimbursement-documents.attachment', $document)
                            : null,
                    ])
                    ->values()
                    ->all(),
            ],
            'backRouteName' => ($this->canTakeApprovalAction($reimbursement, $user) && !$this->canOwnerUpdate($reimbursement, $user))
                ? 'reimbursement-approvals.index'
                : 'reimbursements.index',
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

        if ($isOwner && !$this->canTakeApprovalAction($reimbursement, $user)) {
            if (!$this->canOwnerUpdate($reimbursement, $user)) {
                throw ValidationException::withMessages([
                    'status' => 'Reimbursement form that has been processed cannot be modified by the requester.',
                ]);
            }

            $validated = $request->validate([
                'request_date' => ['required', 'date'],
                'paid_to' => ['required', 'string', 'max:255'],
                'amount_order_meal' => ['required', 'integer', 'min:0'],
                'amount_fuel' => ['required', 'integer', 'min:0'],
                'amount_toll' => ['required', 'integer', 'min:0'],
                'amount_in_words' => ['required', 'string', 'max:255'],
                'expense_type' => ['required', 'string', 'max:255'],
                'purpose' => ['required', 'string'],
                'ref_document' => ['nullable', 'string', 'max:255'],
                'documents' => ['nullable', 'array', 'max:30'],
                'documents.*.id' => ['nullable', 'integer'],
                'documents.*.ref_document' => ['nullable', 'string', 'max:255'],
                'documents.*.attachment_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
                'description' => ['nullable', 'string'],
                'attachment_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            ]);

            $amountOrderMeal = (int) $validated['amount_order_meal'];
            $amountFuel = (int) $validated['amount_fuel'];
            $amountToll = (int) $validated['amount_toll'];
            $totalAmount = $amountOrderMeal + $amountFuel + $amountToll;

            $reimbursement->fill([
                ...$validated,
                'amount' => $totalAmount,
                'amount_order_meal' => $amountOrderMeal,
                'amount_fuel' => $amountFuel,
                'amount_toll' => $amountToll,
            ]);

            $this->syncDocumentsFromRequest($request, $reimbursement);

            $reimbursement->save();
            $this->syncLegacyDocumentSnapshot($reimbursement);

            return redirect()->route('reimbursements.index')->with('success', 'Reimbursement form has been successfully updated.');
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

                /** @var \Illuminate\Database\Eloquent\Collection<int, User> $mds */
                $mds = User::query()
                    ->whereHas('role', fn($q) => $q->where('code', 'md'))
                    ->where('is_available_for_approval', true)
                    ->get();

                foreach ($mds as $md) {
                    $this->notifyReimbursementApproverOnce($md, $reimbursement, 'md');
                }
            }

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Manager approval has been successfully processed.');
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

            return redirect()->route('reimbursement-approvals.index')->with('success', 'MD approval has been successfully processed.');
        }

        if ($this->canSubmitRatna($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING]);
            $reimbursement->ratna_submitted_by = $user?->id;
            $reimbursement->ratna_submitted_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            $this->notifyReimbursementOwner($reimbursement, 'submitted_to_accounting');

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Reimbursement has been successfully submitted to accounting.');
        }

        if ($this->canFinishAccounting($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_FINISHED]);
            $reimbursement->accounting_processed_by = $user?->id;
            $reimbursement->accounting_processed_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Reimbursement has been completed by accounting.');
        }

        throw ValidationException::withMessages([
            'status' => 'You do not have access to process this reimbursement.',
        ]);
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

    private function eligibleExitPermitsForUser(int $userId): array
    {
        return ExitPermit::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereNotNull('attendance_checked_at')
            ->where('has_valid_checkin', true)
            ->whereDoesntHave('reimbursements', function ($query) {
                $query->where('status', Reimbursement::STATUS_FINISHED);
            })
            ->latest('permit_date')
            ->with(['user:id,name', 'requestors:id,exit_permit_id,name,row_number,reimburs_lunch_box', 'costCenter:id,name'])
            ->get(['id', 'user_id', 'permit_date', 'destination', 'reimbursement_amount', 'cost_center_id'])
            ->map(function (ExitPermit $exitPermit): array {
                $requestorNames = $exitPermit->requestors
                    ->filter(fn($requestor) => strtoupper(trim((string) ($requestor->reimburs_lunch_box ?? 'N'))) === 'Y')
                    ->sortBy('row_number')
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all();

                $requestorCount = count($requestorNames);
                $unitAmount = (int) ($exitPermit->reimbursement_amount ?? 0);

                if ($unitAmount <= 0) {
                    $unitAmount = 12000;
                }

                $namePreview = implode(', ', array_slice($requestorNames, 0, 8));
                if ($requestorCount > 8) {
                    $namePreview .= ', ...';
                }

                return [
                    'id' => $exitPermit->id,
                    'label' => $this->exitPermitLabel($exitPermit),
                    'permit_date' => $exitPermit->permit_date ? (string) $exitPermit->permit_date : null,
                    'cost_center_name' => $exitPermit->costCenter?->name,
                    'requestor_count' => $requestorCount,
                    'requestor_names' => $requestorNames,
                    'unit_amount' => $unitAmount,
                    'suggested_amount' => $unitAmount * $requestorCount,
                    'amount_order_meal_default' => $unitAmount * $requestorCount,
                    'amount_fuel_default' => 0,
                    'amount_toll_default' => 0,
                    'paid_to_default' => $exitPermit->user?->name ?? '',
                    'expense_type_default' => 'Reimbursement Exit Permit',
                    'purpose_default' => sprintf(
                        'Lunch box conversion for %d requestor(s) (Exit Permit #%d). Requestors: %s',
                        $requestorCount,
                        $exitPermit->id,
                        $namePreview !== '' ? $namePreview : '-'
                    ),
                    'ref_document_default' => 'AUTO-LUNCH-EP-' . $exitPermit->id,
                    'description_default' => sprintf(
                        'Lunch box allowance converted to reimbursement for %d requestor(s) x Rp %d.',
                        $requestorCount,
                        $unitAmount
                    ),
                ];
            })
            ->values()
            ->all();
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
        return in_array($user?->role?->code, ['manager', 'md', 'hr_manager', 'hr', 'accounting', 'admin'], true);
    }

    private function isRequesterRole($user): bool
    {
        $roleCode = $user?->role?->code;

        if (!$roleCode) {
            return true;
        }

        if (in_array($roleCode, ['user', 'manager', 'md', 'hr_manager', 'admin'], true)) {
            return true;
        }

        return $roleCode === 'hr'
            && strtolower((string) $user?->email) === self::RATNA_EMAIL;
    }

    private function canAccessApprovalMenu($user): bool
    {
        return $this->canViewAllData($user);
    }

    private function transformReimbursementListItem(Reimbursement $reimbursement, $user): array
    {
        return [
            'id' => $reimbursement->id,
            'employee_name' => $reimbursement->user?->name,
            'exit_permit_id' => $reimbursement->exit_permit_id,
            'exit_permit_label' => $this->exitPermitLabel($reimbursement->exitPermit),
            'request_date' => $this->normalizeDateForInput($reimbursement->request_date),
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
            'can_update_request' => $this->canOwnerUpdate($reimbursement, $user),
            'can_take_action' => $this->canTakeApprovalAction($reimbursement, $user),
        ];
    }

    private function canOwnerUpdate(Reimbursement $reimbursement, $user): bool
    {
        return $reimbursement->user_id === $user?->id
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MANAGER;
    }

    private function canApproveManager(Reimbursement $reimbursement, $user): bool
    {
        return in_array($user?->role?->code, ['manager', 'hr_manager', 'admin'], true)
            && $reimbursement->status === Reimbursement::STATUS_PENDING_MANAGER
            && $this->canUserApproveManagerStage($reimbursement, $user);
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

    private function canTakeApprovalAction(Reimbursement $reimbursement, $user): bool
    {
        return $this->canApproveManager($reimbursement, $user)
            || $this->canApproveMd($reimbursement, $user)
            || $this->canSubmitRatna($reimbursement, $user)
            || $this->canFinishAccounting($reimbursement, $user);
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

    private function normalizeApprovalToken(?string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)) ?? '');
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

    private function normalizeDateForInput(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function replaceAttachment(Reimbursement $reimbursement, UploadedFile $file): void
    {
        if ($reimbursement->attachment_path && Storage::exists($reimbursement->attachment_path)) {
            Storage::delete($reimbursement->attachment_path);
        }

        $reimbursement->attachment_path = $file->store('reimbursement-attachments');
        $reimbursement->attachment_original_name = $file->getClientOriginalName();
    }

    private function syncDocumentsFromRequest(Request $request, Reimbursement $reimbursement): void
    {
        $rows = $request->input('documents', []);

        if (!is_array($rows) || count($rows) === 0) {
            if ($request->filled('ref_document') || $request->file('attachment_file')) {
                $rows = [
                    [
                        'ref_document' => (string) $request->input('ref_document', ''),
                    ]
                ];
            } else {
                return;
            }
        }

        $uploadedRows = $request->file('documents', []);
        if (!is_array($uploadedRows)) {
            $uploadedRows = [];
        }

        $legacyAttachmentFile = $request->file('attachment_file');
        $existingDocuments = $reimbursement->documents()->get()->keyBy('id');
        $keptDocumentIds = [];

        foreach (array_values($rows) as $index => $row) {
            $documentId = isset($row['id']) ? (int) $row['id'] : null;
            $refDocument = isset($row['ref_document']) ? trim((string) $row['ref_document']) : null;
            $uploadedFile = $uploadedRows[$index]['attachment_file'] ?? null;

            if (!$uploadedFile && $index === 0 && $legacyAttachmentFile instanceof UploadedFile) {
                $uploadedFile = $legacyAttachmentFile;
            }

            if ($documentId && $existingDocuments->has($documentId)) {
                /** @var ReimbursementDocument $document */
                $document = $existingDocuments->get($documentId);

                $document->ref_document = $refDocument !== '' ? $refDocument : null;

                if ($uploadedFile instanceof UploadedFile) {
                    if ($document->attachment_path && Storage::exists($document->attachment_path)) {
                        Storage::delete($document->attachment_path);
                    }

                    $document->attachment_path = $uploadedFile->store('reimbursement-attachments');
                    $document->attachment_original_name = $uploadedFile->getClientOriginalName();
                }

                if (!$document->ref_document && !$document->attachment_path) {
                    $document->delete();
                    continue;
                }

                $document->sort_order = $index + 1;
                $document->save();
                $keptDocumentIds[] = $document->id;
                continue;
            }

            if ($refDocument === '' && !($uploadedFile instanceof UploadedFile)) {
                continue;
            }

            $newDocument = new ReimbursementDocument([
                'sort_order' => $index + 1,
                'ref_document' => $refDocument !== '' ? $refDocument : null,
            ]);

            if ($uploadedFile instanceof UploadedFile) {
                $newDocument->attachment_path = $uploadedFile->store('reimbursement-attachments');
                $newDocument->attachment_original_name = $uploadedFile->getClientOriginalName();
            }

            $reimbursement->documents()->save($newDocument);
            $keptDocumentIds[] = $newDocument->id;
        }

        $documentsToDelete = $reimbursement->documents()
            ->whereNotIn('id', $keptDocumentIds)
            ->get();

        foreach ($documentsToDelete as $document) {
            if ($document->attachment_path && Storage::exists($document->attachment_path)) {
                Storage::delete($document->attachment_path);
            }

            $document->delete();
        }
    }

    private function syncLegacyDocumentSnapshot(Reimbursement $reimbursement): void
    {
        $firstDocument = $reimbursement->documents()->orderBy('sort_order')->orderBy('id')->first();

        $reimbursement->ref_document = $firstDocument?->ref_document;
        $reimbursement->attachment_path = $firstDocument?->attachment_path;
        $reimbursement->attachment_original_name = $firstDocument?->attachment_original_name;
        $reimbursement->save();
    }
}
