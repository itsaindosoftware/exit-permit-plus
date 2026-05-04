<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\User;
use App\Notifications\ArrangeCarDriverRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExitPermitController extends Controller
{
    private const CAR_DRIVER_COORDINATOR_EMAIL = 'ratna@example.com';

    private const ATTENDANCE_VERIFIER_EMAIL = 'sisca.dewiyani@example.com';

    private const HR_APPROVER_PRIORITY_EMAILS = [
        'wida.mustika.sari@example.com',
        'theresia.saing@example.com',
        'ratna@example.com',
        'sisca.dewiyani@example.com',
    ];

    public function index(): Response
    {
        $user = request()->user();
        $query = ExitPermit::query()->with(['user:id,name', 'hrApprover:id,name'])->latest();

        if (!$this->canViewAllData($user)) {
            $query->where('user_id', $user->id);
        } elseif ($user?->role?->code === 'manager') {
            $query->whereNull('manager_approved_at')->where('status', 'pending');
        } elseif ($user?->role?->code === 'md') {
            $query->whereNotNull('manager_approved_at')
                ->whereNull('md_approved_at')
                ->where('status', 'pending');
        }

        return Inertia::render('ExitPermits/Index', [
            'canCreate' => $user?->role?->code === 'user',
            'viewerRole' => $user?->role?->code,
            'exitPermits' => $query
                ->paginate(10)
                ->through(fn(ExitPermit $exitPermit) => [
                    'id' => $exitPermit->id,
                    'employee_name' => $exitPermit->user?->name,
                    'permit_date' => $exitPermit->permit_date ? (string) $exitPermit->permit_date : null,
                    'start_time' => $this->toHourMinute($exitPermit->start_time),
                    'end_time' => $this->toHourMinute($exitPermit->end_time),
                    'destination' => $exitPermit->destination,
                    'exit_type' => $exitPermit->exit_type,
                    'vehicle_plate' => $exitPermit->vehicle_plate,
                    'returned_to_office' => $exitPermit->returned_to_office,
                    'eligible_for_meal' => $exitPermit->eligible_for_meal,
                    'reimbursement_amount' => $exitPermit->reimbursement_amount,
                    'reason' => $exitPermit->reason,
                    'status' => $exitPermit->status,
                    'post_md_path' => $exitPermit->post_md_path,
                    'approval_stage' => $this->approvalStageLabel($exitPermit),
                    'can_update_request' => $this->canOwnerUpdate($exitPermit, $user),
                    'can_delete' => $this->canOwnerDelete($exitPermit, $user),
                    'can_submit_approval' => $this->canSubmitApproval($exitPermit, $user),
                    'can_arrange_car' => $this->canArrangeCar($exitPermit, $user),
                    'can_verify_attendance' => $this->canVerifyAttendance($exitPermit, $user),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ExitPermits/Create', [
            'exitTypes' => ExitPermit::EXIT_TYPES,
            'reimbursementAmounts' => ExitPermit::REIMBURSEMENT_AMOUNTS,
        ]);
    }

    public function show(ExitPermit $exitPermit): Response
    {
        $this->authorizeView($exitPermit);

        $exitPermit->load([
            'user:id,name,email',
            'hrApprover:id,name',
            'managerApprover:id,name',
            'mdApprover:id,name',
            'hrVerifier:id,name',
            'attendanceChecker:id,name',
        ]);

        return Inertia::render('ExitPermits/Show', [
            'viewerRole' => request()->user()?->role?->code,
            'approvalStage' => $this->approvalStageLabel($exitPermit),
            'exitPermit' => [
                'id' => $exitPermit->id,
                'employee_name' => $exitPermit->user?->name,
                'employee_email' => $exitPermit->user?->email,
                'permit_date' => $exitPermit->permit_date ? (string) $exitPermit->permit_date : null,
                'start_time' => $this->toHourMinute($exitPermit->start_time),
                'end_time' => $this->toHourMinute($exitPermit->end_time),
                'destination' => $exitPermit->destination,
                'exit_type' => $exitPermit->exit_type,
                'vehicle_plate' => $exitPermit->vehicle_plate,
                'returned_to_office' => (bool) $exitPermit->returned_to_office,
                'eligible_for_meal' => (bool) $exitPermit->eligible_for_meal,
                'reimbursement_amount' => $exitPermit->reimbursement_amount,
                'reason' => $exitPermit->reason,
                'notes' => $exitPermit->notes,
                'attachment_original_name' => $exitPermit->attachment_original_name,
                'attachment_url' => $exitPermit->attachment_path
                    ? route('exit-permits.attachment', $exitPermit)
                    : null,
                'status' => $exitPermit->status,
                'manager_approved_by_name' => $exitPermit->managerApprover?->name,
                'manager_approved_at' => optional($exitPermit->manager_approved_at)?->toDateTimeString(),
                'md_approved_by_name' => $exitPermit->mdApprover?->name,
                'md_approved_at' => optional($exitPermit->md_approved_at)?->toDateTimeString(),
                'hr_approver_name' => $exitPermit->hrApprover?->name,
                'hr_verified_by_name' => $exitPermit->hrVerifier?->name,
                'hr_verified_at' => optional($exitPermit->hr_verified_at)?->toDateTimeString(),
                'attendance_checked_by_name' => $exitPermit->attendanceChecker?->name,
                'attendance_checked_at' => optional($exitPermit->attendance_checked_at)?->toDateTimeString(),
                'has_valid_checkin' => $exitPermit->has_valid_checkin,
                'post_md_path' => $exitPermit->post_md_path,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $attachmentPhoto = $request->file('attachment_photo');
        unset($validated['attachment_photo']);

        $exitPermit = new ExitPermit([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        $exitPermit->syncBusinessRules()->save();

        if ($attachmentPhoto) {
            $this->replaceAttachment($exitPermit, $attachmentPhoto);
            $exitPermit->save();
        }

        $exitPermit->load('user:id,name');

        if ($exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
            $ratna = User::query()
                ->where('email', self::CAR_DRIVER_COORDINATOR_EMAIL)
                ->first();

            if ($ratna) {
                $ratna->notify(new ArrangeCarDriverRequested($exitPermit));
            }
        }

        return redirect()->route('exit-permits.index')->with('success', 'Data exit permit berhasil ditambahkan.');
    }

    public function edit(ExitPermit $exitPermit): Response
    {
        $this->authorizeUser($exitPermit);
        $user = request()->user();
        $roleCode = $user?->role?->code;
        $canUpdateRequest = $this->canOwnerUpdate($exitPermit, $user);
        $canSubmitApproval = $this->canSubmitApproval($exitPermit, $user);
        $canArrangeCar = $this->canArrangeCar($exitPermit, $user);

        return Inertia::render('ExitPermits/Edit', [
            'exitPermit' => [
                'id' => $exitPermit->id,
                'permit_date' => $exitPermit->permit_date ? (string) $exitPermit->permit_date : null,
                'start_time' => $this->toHourMinute($exitPermit->start_time),
                'end_time' => $this->toHourMinute($exitPermit->end_time),
                'destination' => $exitPermit->destination,
                'exit_type' => $exitPermit->exit_type,
                'vehicle_plate' => $exitPermit->vehicle_plate,
                'returned_to_office' => $exitPermit->returned_to_office,
                'eligible_for_meal' => $exitPermit->eligible_for_meal,
                'reimbursement_amount' => $exitPermit->reimbursement_amount,
                'reason' => $exitPermit->reason,
                'notes' => $exitPermit->notes,
                'attachment_original_name' => $exitPermit->attachment_original_name,
                'attachment_url' => $exitPermit->attachment_path
                    ? route('exit-permits.attachment', $exitPermit)
                    : null,
                'status' => $exitPermit->status,
                'has_valid_checkin' => $exitPermit->has_valid_checkin,
                'attendance_checked_at' => optional($exitPermit->attendance_checked_at)?->toDateTimeString(),
                'post_md_path' => $exitPermit->post_md_path,
            ],
            'canApprove' => $this->canApprove($user),
            'canUpdateRequest' => $canUpdateRequest,
            'canSubmitApproval' => $canSubmitApproval,
            'canArrangeCar' => $canArrangeCar,
            'canVerifyAttendance' => $this->canVerifyAttendance($exitPermit, $user),
            'viewerRole' => $roleCode,
            'approvalStage' => $this->approvalStageLabel($exitPermit),
            'exitTypes' => ExitPermit::EXIT_TYPES,
            'reimbursementAmounts' => ExitPermit::REIMBURSEMENT_AMOUNTS,
        ]);
    }

    public function update(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $this->authorizeUser($exitPermit);

        $user = $request->user();
        $roleCode = $user?->role?->code;
        $isOwner = $exitPermit->user_id === $user?->id;
        $canApprove = $this->canApprove($user);
        $canArrangeCar = $this->canArrangeCar($exitPermit, $user);
        $attachmentPhoto = $request->file('attachment_photo');
        $validated = [];

        if ($isOwner) {
            $validated = $this->validatedData($request);
            unset($validated['attachment_photo']);

            if ($exitPermit->manager_approved_at || $exitPermit->md_approved_at || $exitPermit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Exit permit yang sudah diproses approval tidak dapat diubah oleh pemohon.',
                ]);
            }

            $exitPermit->fill($validated);
            $exitPermit->syncBusinessRules();

            if ($attachmentPhoto) {
                $this->replaceAttachment($exitPermit, $attachmentPhoto);
            }
        }

        if (!$isOwner && $canApprove) {
            $approval = $this->validateApprovalData($request);
            $newStatus = $approval['status'];

            if ($roleCode === 'manager') {
                if ($exitPermit->manager_approved_at || $exitPermit->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'status' => 'Approval manager sudah diproses sebelumnya.',
                    ]);
                }

                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $this->fillApprovalData($exitPermit, 'manager');

                    if ($newStatus === 'approved') {
                        $hrApproverId = $this->resolveHrApproverId();

                        if (!$hrApproverId) {
                            throw ValidationException::withMessages([
                                'status' => 'PIC HR bertingkat tidak ditemukan. Hubungi Administrator.',
                            ]);
                        }

                        $exitPermit->hr_approver_id = $hrApproverId;
                        $exitPermit->status = 'pending';
                    } else {
                        $exitPermit->status = 'rejected';
                    }
                }
            }

            if ($roleCode === 'md') {
                if (!$exitPermit->manager_approved_at) {
                    throw ValidationException::withMessages([
                        'status' => 'Approval MD hanya bisa dilakukan setelah approval manager.',
                    ]);
                }

                if ($exitPermit->md_approved_at || $exitPermit->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'status' => 'Approval MD sudah diproses atau dokumen sudah final.',
                    ]);
                }

                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $this->fillApprovalData($exitPermit, 'md');
                    $exitPermit->status = $newStatus;

                    if ($newStatus === 'approved' && $exitPermit->hr_approver_id) {
                        $exitPermit->hr_verified_by = $exitPermit->hr_approver_id;
                        $exitPermit->hr_verified_at = now();
                        $exitPermit->attendance_checked_by = null;
                        $exitPermit->attendance_checked_at = null;
                        $exitPermit->has_valid_checkin = null;
                        $exitPermit->post_md_path = null;
                    }
                }
            }
        }

        if (!$isOwner && !$canApprove && !$canArrangeCar && $this->canVerifyAttendance($exitPermit, $user)) {
            $attendanceData = $this->validateAttendanceVerificationData($request, $exitPermit);
            $hasValidCheckin = (bool) $attendanceData['has_valid_checkin'];

            $exitPermit->attendance_checked_by = $user?->id;
            $exitPermit->attendance_checked_at = now();
            $exitPermit->has_valid_checkin = $hasValidCheckin;
            $exitPermit->post_md_path = $hasValidCheckin && (bool) $exitPermit->returned_to_office
                ? ExitPermit::POST_MD_PATH_MEAL
                : ExitPermit::POST_MD_PATH_REIMBURSEMENT;
        }

        if (!$isOwner && !$canApprove && $canArrangeCar) {
            $arrangementData = $this->validateVehicleArrangementData($request, $exitPermit);

            $exitPermit->vehicle_plate = strtoupper((string) $arrangementData['vehicle_plate']);
        }

        if (!$isOwner && !$canApprove && !$canArrangeCar && !$this->canVerifyAttendance($exitPermit, $user)) {
            throw ValidationException::withMessages([
                'status' => 'Anda tidak memiliki akses untuk mengubah data ini.',
            ]);
        }

        $exitPermit->save();

        return redirect()->route('exit-permits.index')->with('success', 'Data exit permit berhasil diperbarui.');
    }

    public function destroy(ExitPermit $exitPermit): RedirectResponse
    {
        $user = request()->user();

        if ($exitPermit->user_id !== $user?->id || $exitPermit->manager_approved_at || $exitPermit->md_approved_at) {
            abort(403);
        }

        if ($exitPermit->attachment_path) {
            Storage::delete($exitPermit->attachment_path);
        }

        $exitPermit->delete();

        return redirect()->route('exit-permits.index')->with('success', 'Data exit permit berhasil dihapus.');
    }

    public function attachment(ExitPermit $exitPermit): StreamedResponse
    {
        $this->authorizeView($exitPermit);

        if (!$exitPermit->attachment_path || !Storage::exists($exitPermit->attachment_path)) {
            abort(404);
        }

        return Storage::response(
            $exitPermit->attachment_path,
            $exitPermit->attachment_original_name ?: basename($exitPermit->attachment_path),
            [
                'Content-Disposition' => 'inline',
            ],
        );
    }

    private function canApprove($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md'], true);
    }

    private function canViewAllData($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md', 'hr', 'admin'], true);
    }

    private function canOwnerUpdate(ExitPermit $exitPermit, $user): bool
    {
        return $exitPermit->user_id === $user?->id
            && !$exitPermit->manager_approved_at
            && !$exitPermit->md_approved_at
            && $exitPermit->status === 'pending';
    }

    private function canOwnerDelete(ExitPermit $exitPermit, $user): bool
    {
        return $this->canOwnerUpdate($exitPermit, $user);
    }

    private function canSubmitApproval(ExitPermit $exitPermit, $user): bool
    {
        if ($user?->role?->code === 'manager') {
            return $exitPermit->status === 'pending' && !$exitPermit->manager_approved_at;
        }

        if ($user?->role?->code === 'md') {
            return $exitPermit->status === 'pending'
                && (bool) $exitPermit->manager_approved_at
                && !$exitPermit->md_approved_at;
        }

        return false;
    }

    private function approvalStageLabel(ExitPermit $exitPermit): string
    {
        $approverName = $exitPermit->hrApprover?->name ?? 'HR Approver';

        if ($exitPermit->status === 'approved') {
            if (!$exitPermit->attendance_checked_at) {
                return 'Approved by MD | Menunggu verifikasi absensi Sisca';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_MEAL) {
                return 'Approved by MD | Jalur Meal';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_REIMBURSEMENT) {
                return 'Approved by MD | Jalur Reimbursement';
            }

            return 'Approved by MD | Diketahui HR (' . $approverName . ')';
        }

        if ($exitPermit->status === 'rejected') {
            return $exitPermit->md_approved_at ? 'Rejected by MD' : 'Rejected by Manager';
        }

        if (!$exitPermit->manager_approved_at) {
            return 'Waiting Manager Approval';
        }

        if (!$exitPermit->md_approved_at) {
            return 'Waiting MD Approval | PIC HR: ' . $approverName;
        }

        return 'Pending';
    }

    private function validatedData(Request $request, bool $allowStatus = false): array
    {
        $validated = $request->validate([
            'permit_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after_or_equal:start_time'],
            'destination' => ['required', 'string', 'max:255'],
            'exit_type' => ['required', Rule::in(ExitPermit::EXIT_TYPES)],
            'vehicle_plate' => [
                'nullable',
                'string',
                'max:20',
            ],
            'returned_to_office' => ['required', 'boolean'],
            'reimbursement_amount' => ['required', 'integer', Rule::in(ExitPermit::REIMBURSEMENT_AMOUNTS)],
            'reason' => ['required', 'string'],
            'notes' => ['required', 'string'],
            'attachment_photo' => ['nullable', 'image', 'max:2048'],
            'status' => $allowStatus ? ['nullable', Rule::in(['pending', 'approved', 'rejected'])] : ['nullable'],
        ]);

        if (!$allowStatus) {
            unset($validated['status']);
        }

        $validated['returned_to_office'] = $request->boolean('returned_to_office');
        $validated['vehicle_plate'] = $validated['exit_type'] === ExitPermit::EXIT_TYPE_BUSINESS_TRIP && filled($validated['vehicle_plate'] ?? null)
            ? strtoupper((string) $validated['vehicle_plate'])
            : null;

        return $validated;
    }

    private function validateApprovalData(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['approved', 'rejected'])],
        ]);

        return [
            'status' => $validated['status'] ?? 'approved',
        ];
    }

    private function validateVehicleArrangementData(Request $request, ExitPermit $exitPermit): array
    {
        if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
            throw ValidationException::withMessages([
                'vehicle_plate' => 'Arrange mobil/supir hanya berlaku untuk tipe perjalanan dinas.',
            ]);
        }

        return $request->validate([
            'vehicle_plate' => ['required', 'string', 'max:20'],
        ]);
    }

    private function validateAttendanceVerificationData(Request $request, ExitPermit $exitPermit): array
    {
        if (!$exitPermit->md_approved_at || $exitPermit->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Verifikasi absensi hanya bisa dilakukan setelah approval MD.',
            ]);
        }

        return $request->validate([
            'has_valid_checkin' => ['required', 'boolean'],
        ]);
    }

    private function toHourMinute(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function fillApprovalData(ExitPermit $exitPermit, ?string $roleCode): void
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

    private function authorizeUser(ExitPermit $exitPermit): void
    {
        $user = request()->user();
        $roleCode = $user?->role?->code;

        if ($exitPermit->user_id === $user?->id) {
            return;
        }

        if ($roleCode === 'manager') {
            if ($exitPermit->status !== 'pending' || $exitPermit->manager_approved_at) {
                abort(403);
            }

            return;
        }

        if ($roleCode === 'md') {
            if ($exitPermit->status !== 'pending' || !$exitPermit->manager_approved_at || $exitPermit->md_approved_at) {
                abort(403);
            }

            return;
        }

        if ($this->canArrangeCar($exitPermit, $user)) {
            return;
        }

        if ($this->canVerifyAttendance($exitPermit, $user)) {
            return;
        }

        if (!in_array($roleCode, ['hr', 'admin'], true)) {
            abort(403);
        }
    }

    private function authorizeView(ExitPermit $exitPermit): void
    {
        $user = request()->user();

        if ($exitPermit->user_id === $user?->id) {
            return;
        }

        if ($this->canViewAllData($user)) {
            return;
        }

        abort(403);
    }

    private function resolveHrApproverId(): ?int
    {
        $approversByEmail = User::query()
            ->select(['id', 'email', 'is_available_for_approval'])
            ->whereIn('email', self::HR_APPROVER_PRIORITY_EMAILS)
            ->whereHas('role', fn($roleQuery) => $roleQuery->whereIn('code', ['manager', 'hr']))
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

    private function canArrangeCar(ExitPermit $exitPermit, $user): bool
    {
        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::CAR_DRIVER_COORDINATOR_EMAIL
            && $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && $exitPermit->status === 'pending';
    }

    private function canVerifyAttendance(ExitPermit $exitPermit, $user): bool
    {
        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::ATTENDANCE_VERIFIER_EMAIL
            && $exitPermit->status === 'approved'
            && (bool) $exitPermit->md_approved_at;
    }

    private function replaceAttachment(ExitPermit $exitPermit, UploadedFile $file): void
    {
        if ($exitPermit->attachment_path && Storage::exists($exitPermit->attachment_path)) {
            Storage::delete($exitPermit->attachment_path);
        }

        $exitPermit->attachment_path = $file->store('exit-permit-attachments');
        $exitPermit->attachment_original_name = $file->getClientOriginalName();
    }
}
