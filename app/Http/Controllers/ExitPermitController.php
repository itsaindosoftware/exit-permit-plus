<?php

namespace App\Http\Controllers;

use App\Models\AttendanceImportLog;
use App\Models\Car;
use App\Models\Driver;
use App\Models\ExitPermit;
use App\Models\User;
use App\Notifications\ArrangeCarDriverRequested;
use App\Services\AttendanceMatchingService;
use App\Services\ExitPermitLunchConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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
        'hr.manager@example.com',
        'wida.mustika.sari@example.com',
        'theresia.saing@example.com',
        'ratna@example.com',
        'sisca.dewiyani@example.com',
    ];

    public function __construct(
        private readonly AttendanceMatchingService $attendanceMatchingService,
        private readonly ExitPermitLunchConversionService $exitPermitLunchConversionService,
    ) {
    }

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
        } elseif ($user?->role?->code === 'hr_manager') {
            $query->whereNotNull('manager_approved_at')
                ->whereNotNull('md_approved_at')
                ->whereNull('hr_verified_at')
                ->where('status', 'pending');
        }

        return Inertia::render('ExitPermits/Index', [
            'canCreate' => (bool) $user,
            'viewerRole' => $user?->role?->code,
            'exitPermits' => $query
                ->paginate(10)
                ->through(fn(ExitPermit $exitPermit) => [
                    'id' => $exitPermit->id,
                    'employee_name' => $exitPermit->user?->name,
                    'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                    'start_time' => $this->toHourMinute($exitPermit->start_time),
                    'end_time' => $this->toHourMinute($exitPermit->end_time),
                    'destination' => $exitPermit->destination,
                    'exit_type' => $exitPermit->exit_type,
                    'vehicle_plate' => $exitPermit->vehicle_plate,
                    'driver_name' => $exitPermit->driver_name,
                    'returned_to_office' => $exitPermit->returned_to_office,
                    'eligible_for_meal' => $exitPermit->eligible_for_meal,
                    'reimbursement_amount' => $exitPermit->reimbursement_amount,
                    'reason' => $exitPermit->reason,
                    'status' => $exitPermit->status,
                    'status_label' => $this->statusLabel($exitPermit),
                    'is_attendance_checked' => (bool) $exitPermit->attendance_checked_at,
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
            'carOptions' => $this->carOptions(),
            'driverOptions' => $this->driverOptions(),
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
            'requestors',
        ]);

        return Inertia::render('ExitPermits/Show', [
            'viewerRole' => request()->user()?->role?->code,
            'approvalStage' => $this->approvalStageLabel($exitPermit),
            'exitPermit' => [
                'id' => $exitPermit->id,
                'employee_name' => $exitPermit->user?->name,
                'employee_email' => $exitPermit->user?->email,
                'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                'start_time' => $this->toHourMinute($exitPermit->start_time),
                'end_time' => $this->toHourMinute($exitPermit->end_time),
                'destination' => $exitPermit->destination,
                'exit_type' => $exitPermit->exit_type,
                'order_car' => (bool) $exitPermit->order_car,
                'vehicle_plate' => $exitPermit->vehicle_plate,
                'driver_name' => $exitPermit->driver_name,
                'returned_to_office' => (bool) $exitPermit->returned_to_office,
                'eligible_for_meal' => (bool) $exitPermit->eligible_for_meal,
                'reimbursement_amount' => $exitPermit->reimbursement_amount,
                'reason' => $exitPermit->reason,
                'notes' => $exitPermit->notes,
                'requestor_items' => $exitPermit->requestors
                    ->map(fn($requestor) => [
                        'name' => $requestor->name,
                        'employee_id' => $requestor->employee_id,
                        'position' => $requestor->position,
                        'department' => $requestor->department,
                        'reimburs_lunch_box' => $requestor->reimburs_lunch_box,
                    ])
                    ->values()
                    ->all(),
                'attachment_original_name' => $exitPermit->attachment_original_name,
                'attachment_url' => $exitPermit->attachment_path
                    ? route('exit-permits.attachment', $exitPermit)
                    : null,
                'status' => $exitPermit->status,
                'status_label' => $this->statusLabel($exitPermit),
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

        $orderCar = (bool) ($validated['order_car'] ?? false);

        $exitPermit = new ExitPermit([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        $exitPermit->syncBusinessRules()->save();
        $this->syncRequestorItems($exitPermit, $validated['requestor_items'] ?? []);

        if ($attachmentPhoto) {
            $this->replaceAttachment($exitPermit, $attachmentPhoto);
            $exitPermit->save();
        }

        $exitPermit->load('user:id,name');

        if ($exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP && $orderCar) {
            $ratna = User::query()
                ->where('email', self::CAR_DRIVER_COORDINATOR_EMAIL)
                ->first();

            if ($ratna) {
                $ratna->notify(new ArrangeCarDriverRequested($exitPermit));
            }
        }

        return redirect()->route('exit-permits.index')->with('success', 'Data exit permit berhasil ditambahkan.');
    }

    public function edit(Request $request, ExitPermit $exitPermit): Response
    {
        $this->authorizeUser($exitPermit);
        $exitPermit->load('requestors');
        $user = request()->user();
        $roleCode = $user?->role?->code;
        $canUpdateRequest = $this->canOwnerUpdate($exitPermit, $user);
        $canSubmitApproval = $this->canSubmitApproval($exitPermit, $user);
        $canArrangeCar = $this->canArrangeCar($exitPermit, $user);

        return Inertia::render('ExitPermits/Edit', [
            'exitPermit' => [
                'id' => $exitPermit->id,
                'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                'start_time' => $this->toHourMinute($exitPermit->start_time),
                'end_time' => $this->toHourMinute($exitPermit->end_time),
                'destination' => $exitPermit->destination,
                'exit_type' => $exitPermit->exit_type,
                'order_car' => (bool) $exitPermit->order_car,
                'vehicle_plate' => $exitPermit->vehicle_plate,
                'driver_name' => $exitPermit->driver_name,
                'car_id' => Car::query()
                    ->where('police_no', $exitPermit->vehicle_plate)
                    ->value('id'),
                'driver_id' => Driver::query()
                    ->where('name', $exitPermit->driver_name)
                    ->value('id'),
                'returned_to_office' => $exitPermit->returned_to_office,
                'eligible_for_meal' => $exitPermit->eligible_for_meal,
                'reimbursement_amount' => $exitPermit->reimbursement_amount,
                'reason' => $exitPermit->reason,
                'notes' => $exitPermit->notes,
                'requestor_items' => $exitPermit->requestors
                    ->map(fn($requestor) => [
                        'name' => $requestor->name,
                        'employee_id' => $requestor->employee_id,
                        'position' => $requestor->position,
                        'department' => $requestor->department,
                        'reimburs_lunch_box' => $requestor->reimburs_lunch_box,
                    ])
                    ->values()
                    ->all(),
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
            'carOptions' => $this->carOptions(),
            'driverOptions' => $this->driverOptions(),
            'attendancePreview' => $request->session()->get($this->attendancePreviewSessionKey($exitPermit->id)),
        ]);
    }

    public function previewAttendance(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $this->authorizeUser($exitPermit);

        if (!$this->canVerifyAttendance($exitPermit, $request->user())) {
            abort(403);
        }

        $this->validateAttendanceVerificationData($request, $exitPermit);

        if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Preview matching attendance hanya berlaku untuk tipe company/business trip.',
            ]);
        }

        $preview = $this->makeAttendancePreview($exitPermit, $request->file('attendance_file'));
        $request->session()->put($this->attendancePreviewSessionKey($exitPermit->id), $preview);

        $this->logAttendanceImport(
            $exitPermit,
            $request->user()?->id,
            $preview,
            'manual_preview',
        );

        return redirect()->route('exit-permits.edit', $exitPermit)
            ->with('success', 'Preview matching attendance berhasil dibuat. Silakan review sebelum simpan verifikasi.');
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
            $this->syncRequestorItems($exitPermit, $validated['requestor_items'] ?? []);

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

                    if ($newStatus === 'approved') {
                        $hrApproverId = $exitPermit->hr_approver_id ?: $this->resolveHrApproverId();

                        if (!$hrApproverId) {
                            throw ValidationException::withMessages([
                                'status' => 'HR Manager approver tidak ditemukan. Hubungi Administrator.',
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
                    } else {
                        $exitPermit->status = 'rejected';
                    }
                }
            }

            if ($roleCode === 'hr_manager') {
                if (!$exitPermit->manager_approved_at || !$exitPermit->md_approved_at) {
                    throw ValidationException::withMessages([
                        'status' => 'Approval HR Manager hanya bisa dilakukan setelah approval MD.',
                    ]);
                }

                if ($exitPermit->status !== 'pending' || $exitPermit->hr_verified_at) {
                    throw ValidationException::withMessages([
                        'status' => 'Approval HR Manager sudah diproses atau dokumen sudah final.',
                    ]);
                }

                if ($exitPermit->hr_approver_id && $exitPermit->hr_approver_id !== $user?->id) {
                    throw ValidationException::withMessages([
                        'status' => 'Dokumen ini ditugaskan ke HR Manager lain sebagai approver.',
                    ]);
                }

                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $exitPermit->hr_verified_by = $user?->id;
                    $exitPermit->hr_verified_at = now();
                    $exitPermit->status = $newStatus;

                    if ($newStatus === 'approved') {
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

            if ($exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
                $cachedPreview = $request->session()->get($this->attendancePreviewSessionKey($exitPermit->id));

                if ($request->file('attendance_file')) {
                    $attendancePreview = $this->makeAttendancePreview($exitPermit, $request->file('attendance_file'));
                } elseif (filled(config('attendance.source_path'))) {
                    $attendancePreview = $this->makeAttendancePreview($exitPermit);
                } elseif (is_array($cachedPreview)) {
                    $attendancePreview = $cachedPreview;
                } else {
                    throw ValidationException::withMessages([
                        'attendance_file' => 'Silakan preview attendance dulu atau upload file attendance sebelum simpan.',
                    ]);
                }

                $matchedCount = $this->attendanceMatchingService->applyPreview($exitPermit, $attendancePreview);
                $hasValidCheckin = (bool) ($attendancePreview['summary']['has_valid_checkin'] ?? false);

                $this->logAttendanceImport(
                    $exitPermit,
                    $user?->id,
                    [
                        ...$attendancePreview,
                        'summary' => [
                            ...($attendancePreview['summary'] ?? []),
                            'matched_count' => $matchedCount,
                            'has_valid_checkin' => $hasValidCheckin,
                        ],
                    ],
                    'manual_applied',
                );

                $request->session()->forget($this->attendancePreviewSessionKey($exitPermit->id));
            } else {
                $hasValidCheckin = (bool) $attendanceData['has_valid_checkin'];
            }

            $exitPermit->attendance_checked_by = $user?->id;
            $exitPermit->attendance_checked_at = now();
            $exitPermit->has_valid_checkin = $hasValidCheckin;
            $exitPermit->post_md_path = $hasValidCheckin && (bool) $exitPermit->returned_to_office
                ? ExitPermit::POST_MD_PATH_MEAL
                : ExitPermit::POST_MD_PATH_REIMBURSEMENT;
        }

        if (!$isOwner && !$canApprove && $canArrangeCar) {
            $arrangementData = $this->validateVehicleArrangementData($request, $exitPermit);

            $selectedCar = Car::query()->find((int) $arrangementData['car_id']);
            $selectedDriver = Driver::query()->find((int) $arrangementData['driver_id']);

            $exitPermit->vehicle_plate = strtoupper((string) ($selectedCar?->police_no ?? ''));
            $exitPermit->driver_name = (string) ($selectedDriver?->name ?? '');
        }

        if (!$isOwner && !$canApprove && !$canArrangeCar && !$this->canVerifyAttendance($exitPermit, $user)) {
            throw ValidationException::withMessages([
                'status' => 'Anda tidak memiliki akses untuk mengubah data ini.',
            ]);
        }

        $exitPermit->save();

        if ($this->canVerifyAttendance($exitPermit, $user)) {
            $this->exitPermitLunchConversionService->applyIfEligible($exitPermit->fresh(['requestors', 'user']));
        }

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
        return in_array($user?->role?->code, ['manager', 'md', 'hr_manager'], true);
    }

    private function canViewAllData($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md', 'hr_manager', 'hr', 'admin'], true);
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

        if ($user?->role?->code === 'hr_manager') {
            return $exitPermit->status === 'pending'
                && (bool) $exitPermit->manager_approved_at
                && (bool) $exitPermit->md_approved_at
                && !$exitPermit->hr_verified_at
                && (!$exitPermit->hr_approver_id || $exitPermit->hr_approver_id === $user?->id);
        }

        return false;
    }

    private function approvalStageLabel(ExitPermit $exitPermit): string
    {
        $approverName = $exitPermit->hrApprover?->name ?? 'HR Approver';

        if ($exitPermit->status === 'approved') {
            if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
                return 'Approved by HR Manager | Selesai (Diketahui Sisca)';
            }

            if (!$exitPermit->attendance_checked_at) {
                return 'Approved by HR Manager | Menunggu verifikasi absensi Sisca';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_MEAL) {
                return 'Approved by HR Manager | Jalur Meal';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_REIMBURSEMENT) {
                return 'Approved by HR Manager | Jalur Reimbursement';
            }

            return 'Approved by HR Manager | Sepengetahuan HR Manager (' . $approverName . ')';
        }

        if ($exitPermit->status === 'rejected') {
            if ($exitPermit->hr_verified_at) {
                return 'Rejected by HR Manager';
            }

            return $exitPermit->md_approved_at ? 'Rejected by MD' : 'Rejected by Manager';
        }

        if (!$exitPermit->manager_approved_at) {
            return 'Waiting Manager Approval';
        }

        if (!$exitPermit->md_approved_at) {
            return 'Waiting MD Approval | PIC HR: ' . $approverName;
        }

        if (!$exitPermit->hr_verified_at) {
            return 'Waiting HR Manager Approval | PIC HR: ' . $approverName;
        }

        return 'Pending';
    }

    private function statusLabel(ExitPermit $exitPermit): string
    {
        if (
            $exitPermit->status === 'approved'
            && $exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && (bool) $exitPermit->hr_verified_at
        ) {
            return 'Diketahui Sisca (HRD)';
        }

        if (
            $exitPermit->status === 'approved'
            && (bool) $exitPermit->attendance_checked_at
            && (bool) $exitPermit->has_valid_checkin
        ) {
            return 'Checked By HR: Sisca';
        }

        return strtoupper((string) $exitPermit->status);
    }

    private function validatedData(Request $request, bool $allowStatus = false): array
    {
        $validated = $request->validate([
            'permit_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after_or_equal:start_time'],
            'destination' => ['required', 'string', 'max:255'],
            'exit_type' => ['required', Rule::in(ExitPermit::EXIT_TYPES)],
            'requestor_items' => ['required', 'array', 'min:1'],
            'requestor_items.*.name' => ['required', 'string', 'max:120'],
            'requestor_items.*.employee_id' => ['nullable', 'string', 'max:60'],
            'requestor_items.*.position' => ['nullable', 'string', 'max:120'],
            'requestor_items.*.department' => ['nullable', 'string', 'max:120'],
            'requestor_items.*.reimburs_lunch_box' => ['nullable', 'string', 'max:10'],
            'order_car' => ['nullable', 'boolean'],
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'returned_to_office' => ['required', 'boolean'],
            'reimbursement_amount' => ['nullable'],
            'reason' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'attachment_photo' => ['nullable', 'image', 'max:2048'],
            'status' => $allowStatus ? ['nullable', Rule::in(['pending', 'approved', 'rejected'])] : ['nullable'],
        ]);

        if (!$allowStatus) {
            unset($validated['status']);
        }

        $validated['order_car'] = $request->boolean('order_car');
        $validated['returned_to_office'] = $request->boolean('returned_to_office');

        if (
            $validated['exit_type'] === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && $validated['order_car']
            && !empty($validated['car_id'])
            && !empty($validated['driver_id'])
        ) {
            $selectedCar = Car::query()->find((int) ($validated['car_id'] ?? 0));
            $selectedDriver = Driver::query()->find((int) ($validated['driver_id'] ?? 0));

            $validated['vehicle_plate'] = strtoupper((string) ($selectedCar?->police_no ?? ''));
            $validated['driver_name'] = (string) ($selectedDriver?->name ?? '');
        } else {
            $validated['vehicle_plate'] = null;
            $validated['driver_name'] = null;
        }

        // Field Permitted by/notes dinonaktifkan sementara dari alur form.
        $validated['notes'] = null;

        $validated['reimbursement_amount'] = 0;

        $validated['requestor_items'] = collect($validated['requestor_items'] ?? [])
            ->map(fn(array $item, int $index) => [
                'row_number' => $index + 1,
                'name' => (string) ($item['name'] ?? ''),
                'employee_id' => filled($item['employee_id'] ?? null) ? (string) $item['employee_id'] : null,
                'position' => filled($item['position'] ?? null) ? (string) $item['position'] : null,
                'department' => filled($item['department'] ?? null) ? (string) $item['department'] : null,
                'reimburs_lunch_box' => filled($item['reimburs_lunch_box'] ?? null)
                    ? strtoupper(trim((string) $item['reimburs_lunch_box']))
                    : null,
            ])
            ->values()
            ->all();

        unset($validated['car_id'], $validated['driver_id']);

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
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);
    }

    private function carOptions(): array
    {
        return Car::query()
            ->where('status', 'ACTIVE')
            ->orderBy('spesification')
            ->get(['id', 'police_no', 'spesification'])
            ->map(fn(Car $car) => [
                'id' => $car->id,
                'police_no' => $car->police_no,
                'spesification' => $car->spesification,
            ])
            ->values()
            ->all();
    }

    private function driverOptions(): array
    {
        return Driver::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Driver $driver) => [
                'id' => $driver->id,
                'name' => $driver->name,
            ])
            ->values()
            ->all();
    }

    private function syncRequestorItems(ExitPermit $exitPermit, array $requestorItems): void
    {
        $exitPermit->requestors()->delete();

        if (count($requestorItems) === 0) {
            return;
        }

        $exitPermit->requestors()->createMany($requestorItems);
    }

    private function validateAttendanceVerificationData(Request $request, ExitPermit $exitPermit): array
    {
        if (!$exitPermit->md_approved_at || !$exitPermit->hr_verified_at || $exitPermit->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Verifikasi absensi hanya bisa dilakukan setelah approval HR Manager.',
            ]);
        }

        if ($exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
            return $request->validate([
                'attendance_file' => ['nullable', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
            ]);
        }

        return $request->validate([
            'has_valid_checkin' => ['required', 'boolean'],
            'attendance_file' => ['nullable', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
        ]);
    }

    private function attendancePreviewSessionKey(int $exitPermitId): string
    {
        return 'attendance_preview_' . $exitPermitId;
    }

    private function makeAttendancePreview(ExitPermit $exitPermit, ?UploadedFile $uploadedFile = null): array
    {
        $sourcePayload = $this->attendanceMatchingService->loadRowsWithSource($uploadedFile);

        return $this->attendanceMatchingService->buildPreview(
            $exitPermit,
            $sourcePayload['rows'],
            $sourcePayload['source'],
        );
    }

    private function logAttendanceImport(ExitPermit $exitPermit, ?int $userId, array $preview, string $importType): void
    {
        $summary = (array) ($preview['summary'] ?? []);
        $source = (array) ($preview['source'] ?? []);

        AttendanceImportLog::query()->create([
            'exit_permit_id' => $exitPermit->id,
            'user_id' => $userId,
            'source_disk' => $source['disk'] ?? null,
            'source_path' => $source['path'] ?? null,
            'source_file_name' => $source['file_name'] ?? null,
            'import_type' => $importType,
            'imported_at' => now(),
            'total_requestors' => (int) ($summary['total_requestors'] ?? 0),
            'matched_count' => (int) ($summary['matched_count'] ?? 0),
            'has_valid_checkin' => (bool) ($summary['has_valid_checkin'] ?? false),
        ]);
    }

    private function loadAttendanceRows(Request $request): Collection
    {
        $uploadedFile = $request->file('attendance_file');

        if ($uploadedFile instanceof UploadedFile) {
            return $this->parseAttendanceFile(
                (string) $uploadedFile->getRealPath(),
                strtolower((string) $uploadedFile->getClientOriginalExtension()),
            );
        }

        $disk = (string) config('attendance.source_disk', 'local');
        $path = trim((string) config('attendance.source_path', ''), '/');

        if ($path === '') {
            throw ValidationException::withMessages([
                'attendance_file' => 'File attendance wajib diupload atau set ATTENDANCE_SOURCE_PATH terlebih dahulu.',
            ]);
        }

        $diskInstance = Storage::disk($disk);
        $files = collect($diskInstance->files($path))
            ->filter(function (string $filePath) {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                return in_array($extension, ['csv', 'txt', 'xlsx'], true);
            })
            ->sortByDesc(fn(string $filePath) => $diskInstance->lastModified($filePath))
            ->values();

        $latestFile = $files->first();

        if (!$latestFile) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Tidak ada file attendance (csv/xlsx) di folder sharing.',
            ]);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'attendance_');

        if ($tempPath === false) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Gagal menyiapkan file sementara attendance.',
            ]);
        }

        file_put_contents($tempPath, (string) $diskInstance->get($latestFile));

        try {
            $extension = strtolower((string) pathinfo($latestFile, PATHINFO_EXTENSION));
            return $this->parseAttendanceFile($tempPath, $extension);
        } finally {
            @unlink($tempPath);
        }
    }

    private function parseAttendanceFile(string $filePath, string $extension): Collection
    {
        $rawRows = match ($extension) {
            'csv', 'txt' => $this->readCsvRows($filePath),
            'xlsx' => $this->readXlsxRows($filePath),
            default => throw ValidationException::withMessages([
                'attendance_file' => 'Format file attendance belum didukung. Gunakan CSV atau XLSX.',
            ]),
        };

        if (count($rawRows) < 2) {
            throw ValidationException::withMessages([
                'attendance_file' => 'File attendance kosong atau tidak memiliki header + data.',
            ]);
        }

        $headers = array_map(fn($value) => $this->normalizeHeader((string) $value), $rawRows[0]);

        return collect(array_slice($rawRows, 1))
            ->map(function (array $row) use ($headers) {
                $assoc = [];

                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $assoc[$header] = trim((string) ($row[$index] ?? ''));
                }

                return $assoc;
            })
            ->filter(fn(array $row) => count(array_filter($row, fn($value) => $value !== '')) > 0)
            ->map(function (array $row) {
                $employeeId = $this->firstFilled($row, [
                    'EMPLOYEEID',
                    'EMPID',
                    'NIK',
                    'BADGENUMBER',
                    'ENROLLNUMBER',
                    'USERID',
                    'CARDNO',
                    'PIN',
                ]);

                $employeeName = $this->firstFilled($row, [
                    'NAME',
                    'EMPLOYEENAME',
                    'USER',
                    'USERNAME',
                ]);

                $dateTimeValue = $this->firstFilled($row, [
                    'DATETIME',
                    'CHECKINTIME',
                    'CHECKTIME',
                    'LOGTIME',
                    'ATTENDANCETIME',
                    'TIMESTAMP',
                    'TRANSACTIONTIME',
                ]);

                $dateValue = $this->firstFilled($row, [
                    'DATE',
                    'CHECKINDATE',
                    'ATTENDANCEDATE',
                    'TRANSACTIONDATE',
                ]);

                $timeValue = $this->firstFilled($row, [
                    'TIME',
                    'CLOCKTIME',
                    'CHECKIN',
                    'INTIME',
                ]);

                if ($dateTimeValue === null && $dateValue !== null) {
                    $dateTimeValue = trim($dateValue . ' ' . ($timeValue ?? '00:00:00'));
                }

                $attendanceDate = $this->normalizeDate($dateTimeValue);

                return [
                    'employee_id' => $this->normalizeEmployeeId($employeeId),
                    'name' => $this->normalizePersonName($employeeName),
                    'attendance_date' => $attendanceDate,
                ];
            })
            ->filter(fn(array $row) => $row['attendance_date'] !== null)
            ->values();
    }

    private function syncRequestorAttendance(ExitPermit $exitPermit, Collection $attendanceRows): bool
    {
        $permitDate = $this->toDateOnly($exitPermit->permit_date);
        $allowedDepartments = collect(config('attendance.company_departments', ['BIPO', 'INTERNSHIP', 'OUTSOURCE']))
            ->map(fn($department) => strtoupper(trim((string) $department)))
            ->filter()
            ->values()
            ->all();

        $attendanceEmployeeIds = $attendanceRows
            ->where('attendance_date', $permitDate)
            ->pluck('employee_id')
            ->filter()
            ->unique();

        $attendanceNames = $attendanceRows
            ->where('attendance_date', $permitDate)
            ->pluck('name')
            ->filter()
            ->unique();

        $hasValidCheckin = false;

        $exitPermit->requestors()->get()->each(function ($requestor) use ($attendanceEmployeeIds, $attendanceNames, $allowedDepartments, &$hasValidCheckin, ) {
            $department = strtoupper(trim((string) ($requestor->department ?? '')));
            $isCompanyDepartment = in_array($department, $allowedDepartments, true);

            $requestorEmployeeId = $this->normalizeEmployeeId($requestor->employee_id);
            $requestorName = $this->normalizePersonName($requestor->name);

            $foundByEmployeeId = $requestorEmployeeId && $attendanceEmployeeIds->contains($requestorEmployeeId);
            $foundByName = !$foundByEmployeeId && $requestorName && $attendanceNames->contains($requestorName);

            $isAttended = $isCompanyDepartment && ($foundByEmployeeId || $foundByName);
            $requestor->reimburs_lunch_box = $isAttended ? 'Y' : 'N';
            $requestor->save();

            if ($isAttended) {
                $hasValidCheckin = true;
            }
        });

        return $hasValidCheckin;
    }

    private function readCsvRows(string $filePath): array
    {
        $rows = [];
        $file = new \SplFileObject($filePath, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        foreach ($file as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (count($row) === 1 && ($row[0] === null || trim((string) $row[0]) === '')) {
                continue;
            }

            $rows[] = array_map(fn($value) => is_string($value) ? trim($value) : (string) $value, $row);
        }

        return $rows;
    }

    private function readXlsxRows(string $filePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw ValidationException::withMessages([
                'attendance_file' => 'File XLSX tidak dapat dibuka.',
            ]);
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStringXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if (!$sheetXml) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Worksheet XLSX tidak ditemukan.',
            ]);
        }

        $sharedStrings = [];

        if ($sharedStringXml) {
            $shared = simplexml_load_string($sharedStringXml);
            if ($shared) {
                foreach ($shared->si as $item) {
                    $text = '';

                    if (isset($item->t)) {
                        $text = (string) $item->t;
                    } elseif (isset($item->r)) {
                        foreach ($item->r as $run) {
                            $text .= (string) ($run->t ?? '');
                        }
                    }

                    $sharedStrings[] = $text;
                }
            }
        }

        $sheet = simplexml_load_string($sheetXml);

        if (!$sheet || !isset($sheet->sheetData)) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Format sheet XLSX tidak valid.',
            ]);
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $columnLetters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
                $columnIndex = $this->columnLettersToIndex($columnLetters);

                if ($columnIndex < 0) {
                    continue;
                }

                $type = (string) ($cell['t'] ?? '');
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $sharedIndex = (int) $value;
                    $value = $sharedStrings[$sharedIndex] ?? '';
                }

                $cells[$columnIndex] = trim((string) $value);
            }

            if (count($cells) === 0) {
                continue;
            }

            ksort($cells);
            $maxIndex = max(array_keys($cells));
            $normalizedRow = [];

            for ($index = 0; $index <= $maxIndex; $index++) {
                $normalizedRow[] = $cells[$index] ?? '';
            }

            $rows[] = $normalizedRow;
        }

        return $rows;
    }

    private function columnLettersToIndex(string $letters): int
    {
        if ($letters === '') {
            return -1;
        }

        $index = 0;

        foreach (str_split($letters) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return $index - 1;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtoupper(trim($header));
        return preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';
    }

    private function firstFilled(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeEmployeeId(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePersonName(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial > 10000) {
                try {
                    return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
                } catch (\Throwable $exception) {
                }
            }
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
        ];

        foreach ($formats as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, trim($value))->toDateString();
            } catch (\Throwable $exception) {
            }
        }

        try {
            return \Carbon\Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function toHourMinute(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function toDateOnly(mixed $date): ?string
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return substr((string) $date, 0, 10);
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

        if (in_array($roleCode, ['manager', 'hr_manager'], true)) {
            if ($roleCode === 'manager' && ($exitPermit->status !== 'pending' || $exitPermit->manager_approved_at)) {
                abort(403);
            }

            if ($roleCode === 'hr_manager') {
                $canApproveAsHrManager = $exitPermit->status === 'pending'
                    && (bool) $exitPermit->manager_approved_at
                    && (bool) $exitPermit->md_approved_at
                    && !$exitPermit->hr_verified_at
                    && (!$exitPermit->hr_approver_id || $exitPermit->hr_approver_id === $user?->id);

                if (!$canApproveAsHrManager) {
                    abort(403);
                }
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

    private function canArrangeCar(ExitPermit $exitPermit, $user): bool
    {
        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::CAR_DRIVER_COORDINATOR_EMAIL
            && $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && (bool) $exitPermit->order_car
            && $exitPermit->status === 'pending';
    }

    private function canVerifyAttendance(ExitPermit $exitPermit, $user): bool
    {
        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::ATTENDANCE_VERIFIER_EMAIL
            && $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && $exitPermit->status === 'approved'
            && (bool) $exitPermit->md_approved_at
            && (bool) $exitPermit->hr_verified_at;
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
