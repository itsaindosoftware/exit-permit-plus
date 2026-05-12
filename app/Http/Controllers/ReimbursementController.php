<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\Reimbursement;
use App\Models\ReimbursementDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReimbursementController extends Controller
{
    private const RATNA_EMAIL = 'ratna@example.com';

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
        $isRequester = $this->isRequesterRole($user);
        $eligibleExitPermits = $isRequester ? $this->eligibleExitPermitsForUser($user->id) : [];
        $query = Reimbursement::query()
            ->with(['user:id,name', 'exitPermit:id,permit_date,destination'])
            ->latest();

        $query->where('user_id', $user?->id);

        return Inertia::render('Reimbursements/Index', [
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'personal',
            'isRequester' => $isRequester,
            'canCreate' => $isRequester && count($eligibleExitPermits) > 0,
            'eligibleExitPermits' => $eligibleExitPermits,
            'reimbursements' => $query
                ->paginate(10)
                ->through(fn(Reimbursement $reimbursement) => $this->transformReimbursementListItem($reimbursement, $user)),
        ]);
    }

    public function approvalIndex(): Response
    {
        $user = request()->user();

        if (!$this->canAccessApprovalMenu($user)) {
            abort(403);
        }

        $query = Reimbursement::query()
            ->with(['user:id,name', 'exitPermit:id,permit_date,destination'])
            ->latest();

        return Inertia::render('Reimbursements/Index', [
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'approval',
            'isRequester' => false,
            'canCreate' => false,
            'eligibleExitPermits' => [],
            'reimbursements' => $query
                ->paginate(10)
                ->through(fn(Reimbursement $reimbursement) => $this->transformReimbursementListItem($reimbursement, $user)),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $user = request()->user();

        if (!$this->isRequesterRole($user)) {
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

        if (!$this->isRequesterRole($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'exit_permit_id' => ['required', 'integer', 'exists:exit_permits,id'],
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
            'documents.*.ref_document' => ['nullable', 'string', 'max:255'],
            'documents.*.attachment_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'description' => ['nullable', 'string'],
            'attachment_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $exitPermit = ExitPermit::query()->findOrFail($validated['exit_permit_id']);
        $this->ensureEligibleForReimbursementPath($exitPermit, $user->id);

        $alreadyFinished = Reimbursement::query()
            ->where('exit_permit_id', $exitPermit->id)
            ->where('status', Reimbursement::STATUS_FINISHED)
            ->exists();

        if ($alreadyFinished) {
            throw ValidationException::withMessages([
                'exit_permit_id' => 'Reimbursement untuk Exit Permit ini sudah berstatus Sudah Dibayarkan oleh Accounting.',
            ]);
        }

        $amountOrderMeal = (int) $validated['amount_order_meal'];
        $amountFuel = (int) $validated['amount_fuel'];
        $amountToll = (int) $validated['amount_toll'];
        $totalAmount = $amountOrderMeal + $amountFuel + $amountToll;

        $reimbursement = Reimbursement::create([
            'exit_permit_id' => $exitPermit->id,
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

        return redirect()->route('reimbursements.index')->with('success', 'Form reimbursement berhasil diajukan.');
    }

    public function edit(Reimbursement $reimbursement): Response
    {
        $this->authorizeUser($reimbursement);
        $reimbursement->load('documents');

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
                    'status' => 'Form reimbursement yang sudah diproses tidak dapat diubah oleh pemohon.',
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

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Approval manager berhasil diproses.');
        }

        if ($this->canApproveMd($reimbursement, $user)) {
            $status = $this->validateStatus($request, ['approved', 'rejected']);
            $reimbursement->md_approved_by = $user?->id;
            $reimbursement->md_approved_at = now();
            $reimbursement->status = $status === 'approved'
                ? Reimbursement::STATUS_PENDING_RATNA
                : Reimbursement::STATUS_REJECTED;
            $reimbursement->save();

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Approval MD berhasil diproses.');
        }

        if ($this->canSubmitRatna($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING]);
            $reimbursement->ratna_submitted_by = $user?->id;
            $reimbursement->ratna_submitted_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Reimbursement berhasil disubmit ke accounting.');
        }

        if ($this->canFinishAccounting($reimbursement, $user)) {
            $status = $this->validateStatus($request, [Reimbursement::STATUS_FINISHED]);
            $reimbursement->accounting_processed_by = $user?->id;
            $reimbursement->accounting_processed_at = now();
            $reimbursement->status = $status;
            $reimbursement->save();

            return redirect()->route('reimbursement-approvals.index')->with('success', 'Reimbursement telah diselesaikan oleh accounting.');
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
            ->whereNotNull('attendance_checked_at')
            ->where('has_valid_checkin', true)
            ->whereDoesntHave('reimbursements', function ($query) {
                $query->where('status', Reimbursement::STATUS_FINISHED);
            })
            ->latest('permit_date')
            ->with(['user:id,name', 'requestors:id,exit_permit_id,name,row_number,reimburs_lunch_box'])
            ->get(['id', 'user_id', 'permit_date', 'destination', 'reimbursement_amount'])
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
                        'Konversi lunch box untuk %d requestor (Exit Permit #%d). Requestor: %s',
                        $requestorCount,
                        $exitPermit->id,
                        $namePreview !== '' ? $namePreview : '-'
                    ),
                    'ref_document_default' => 'AUTO-LUNCH-EP-' . $exitPermit->id,
                    'description_default' => sprintf(
                        'Jatah lunch box dikonversi menjadi reimbursement untuk %d requestor x Rp %d.',
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
                'exit_permit_id' => 'Exit Permit tidak dimiliki oleh user yang sedang login.',
            ]);
        }

        $isEligible = $exitPermit->status === 'approved'
            && (bool) $exitPermit->attendance_checked_at
            && (bool) $exitPermit->has_valid_checkin;

        if ($isEligible) {
            return;
        }

        throw ValidationException::withMessages([
            'exit_permit_id' => 'Exit Permit belum berstatus Checked By HR: Sisca.',
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

        if (in_array($roleCode, ['user', 'manager', 'md', 'hr_manager'], true)) {
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
        return in_array($user?->role?->code, ['manager', 'hr_manager'], true)
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
            Reimbursement::STATUS_PENDING_RATNA => 'Menunggu Ratna Check & Submit Accounting',
            Reimbursement::STATUS_SUBMITTED_TO_ACCOUNTING => 'Menunggu Proses Accounting',
            Reimbursement::STATUS_FINISHED => 'Sudah Dibayarkan oleh Accounting',
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
