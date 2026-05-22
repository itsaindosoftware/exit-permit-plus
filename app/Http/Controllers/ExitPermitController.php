<?php

namespace App\Http\Controllers;

use App\Models\AttendanceImportLog;
use App\Models\Car;
use App\Models\CostCenter;
use App\Models\Driver;
use App\Models\ExitPermit;
use App\Models\User;
use App\Notifications\ArrangeCarDriverRequested;
use App\Notifications\ExitPermitStatusUpdated;
use App\Notifications\ExitPermitApprovalRequested;
use App\Notifications\ReimbursementSubmissionRequested;
use App\Services\AttendanceMatchingService;
use App\Services\ExitPermitLunchConversionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExitPermitController extends Controller
{
    private const CAR_DRIVER_COORDINATOR_EMAIL = 'hrga-01@thaisummit.co.id';

    private const ATTENDANCE_VERIFIER_EMAIL = 'payroll.hr@thaisummit.co.id';

    private const HR_APPROVER_PRIORITY_EMAILS = [
        'hr.manager@example.com',
        'wida.mustika.sari@example.com',
        'wida.mus@thaisummit.co.id',
        'theresia.saing@example.com',
        'hrga-01@thaisummit.co.id',
        'payroll.hr@thaisummit.co.id',
    ];

    public function __construct(
        private readonly AttendanceMatchingService $attendanceMatchingService,
        private readonly ExitPermitLunchConversionService $exitPermitLunchConversionService,
    ) {
    }

    public function index(): Response
    {
        $user = request()->user();
        $submitter = trim((string) request()->query('submitter', ''));
        $requestor = trim((string) request()->query('requestor', ''));
        $permitDate = trim((string) request()->query('date', ''));
        $month = (int) request()->query('month', 0);
        $year = (int) request()->query('year', 0);
        $exitType = trim((string) request()->query('exit_type', ''));
        $destination = trim((string) request()->query('destination', ''));

        $query = ExitPermit::query()
            ->with(['user:id,name', 'hrApprover:id,name', 'requestors:id,exit_permit_id,name'])
            ->where('user_id', $user?->id)
            ->latest();

        if ($submitter !== '') {
            $query->whereHas('user', function ($subQuery) use ($submitter) {
                $subQuery->where('name', 'like', '%' . $submitter . '%');
            });
        }

        if ($requestor !== '') {
            $query->whereHas('requestors', function ($subQuery) use ($requestor) {
                $subQuery->where('name', 'like', '%' . $requestor . '%');
            });
        }

        if ($permitDate !== '') {
            $query->whereDate('permit_date', $permitDate);
        }

        if ($month >= 1 && $month <= 12) {
            $query->whereMonth('permit_date', $month);
        }

        if ($year >= 1900 && $year <= 3000) {
            $query->whereYear('permit_date', $year);
        }

        if ($exitType !== '' && in_array($exitType, ExitPermit::EXIT_TYPES, true)) {
            $query->where('exit_type', $exitType);
        }

        if ($destination !== '') {
            $query->where('destination', 'like', '%' . $destination . '%');
        }

        return Inertia::render('ExitPermits/Index', [
            'canCreate' => (bool) $user,
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'personal',
            'filters' => [
                'submitter' => $submitter,
                'requestor' => $requestor,
                'date' => $permitDate,
                'month' => $month >= 1 && $month <= 12 ? (string) $month : '',
                'year' => $year >= 1900 && $year <= 3000 ? (string) $year : '',
                'exit_type' => $exitType,
                'destination' => $destination,
            ],
            'exitTypes' => ExitPermit::EXIT_TYPES,
            'exitPermits' => $query
                ->paginate(10)
                ->withQueryString()
                ->through(fn(ExitPermit $exitPermit) => $this->transformExitPermitListItem($exitPermit, $user)),
        ]);
    }

    public function approvalIndex(): Response
    {
        $user = request()->user();
        $isDualApprovalUser = (bool) ($user?->isWidaMustikaSari() ?? false);
        $submitter = trim((string) request()->query('submitter', ''));
        $requestor = trim((string) request()->query('requestor', ''));
        $permitDate = trim((string) request()->query('date', ''));
        $month = (int) request()->query('month', 0);
        $year = (int) request()->query('year', 0);
        $exitType = trim((string) request()->query('exit_type', ''));
        $destination = trim((string) request()->query('destination', ''));

        if (!$this->canAccessApprovalMenu($user)) {
            abort(403);
        }

        $query = ExitPermit::query()->with(['user:id,name', 'hrApprover:id,name', 'requestors:id,exit_permit_id,name'])->latest();

        if ($isDualApprovalUser) {
            $query->where(function ($subQuery) {
                $subQuery->where(function ($managerQuery) {
                    $managerQuery->whereNull('manager_approved_at')->where('status', 'pending');
                })->orWhere(function ($hrManagerQuery) {
                    $hrManagerQuery->whereNotNull('manager_approved_at')
                        ->whereNotNull('md_approved_at')
                        ->whereNull('hr_verified_at')
                        ->where('status', 'pending');
                });
            });
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

        if ($submitter !== '') {
            $query->whereHas('user', function ($subQuery) use ($submitter) {
                $subQuery->where('name', 'like', '%' . $submitter . '%');
            });
        }

        if ($requestor !== '') {
            $query->whereHas('requestors', function ($subQuery) use ($requestor) {
                $subQuery->where('name', 'like', '%' . $requestor . '%');
            });
        }

        if ($permitDate !== '') {
            $query->whereDate('permit_date', $permitDate);
        }

        if ($month >= 1 && $month <= 12) {
            $query->whereMonth('permit_date', $month);
        }

        if ($year >= 1900 && $year <= 3000) {
            $query->whereYear('permit_date', $year);
        }

        if ($exitType !== '' && in_array($exitType, ExitPermit::EXIT_TYPES, true)) {
            $query->where('exit_type', $exitType);
        }

        if ($destination !== '') {
            $query->where('destination', 'like', '%' . $destination . '%');
        }

        return Inertia::render('ExitPermits/Index', [
            'canCreate' => false,
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'approval',
            'filters' => [
                'submitter' => $submitter,
                'requestor' => $requestor,
                'date' => $permitDate,
                'month' => $month >= 1 && $month <= 12 ? (string) $month : '',
                'year' => $year >= 1900 && $year <= 3000 ? (string) $year : '',
                'exit_type' => $exitType,
                'destination' => $destination,
            ],
            'exitTypes' => ExitPermit::EXIT_TYPES,
            'exitPermits' => $query
                ->paginate(10)
                ->withQueryString()
                ->through(fn(ExitPermit $exitPermit) => $this->transformExitPermitListItem($exitPermit, $user)),
        ]);
    }

    public function historyIndex(): Response
    {
        $user = request()->user();
        $submitter = trim((string) request()->query('submitter', ''));
        $requestor = trim((string) request()->query('requestor', ''));
        $permitDate = trim((string) request()->query('date', ''));
        $month = (int) request()->query('month', 0);
        $year = (int) request()->query('year', 0);
        $exitType = trim((string) request()->query('exit_type', ''));
        $destination = trim((string) request()->query('destination', ''));

        if (!$this->canApprove($user)) {
            abort(403);
        }

        $query = ExitPermit::query()
            ->with(['user:id,name', 'hrApprover:id,name', 'requestors:id,exit_permit_id,name'])
            ->where('status', 'approved')
            ->latest();

        if ($submitter !== '') {
            $query->whereHas('user', function ($subQuery) use ($submitter) {
                $subQuery->where('name', 'like', '%' . $submitter . '%');
            });
        }

        if ($requestor !== '') {
            $query->whereHas('requestors', function ($subQuery) use ($requestor) {
                $subQuery->where('name', 'like', '%' . $requestor . '%');
            });
        }

        if ($permitDate !== '') {
            $query->whereDate('permit_date', $permitDate);
        }

        if ($month >= 1 && $month <= 12) {
            $query->whereMonth('permit_date', $month);
        }

        if ($year >= 1900 && $year <= 3000) {
            $query->whereYear('permit_date', $year);
        }

        if ($exitType !== '' && in_array($exitType, ExitPermit::EXIT_TYPES, true)) {
            $query->where('exit_type', $exitType);
        }

        if ($destination !== '') {
            $query->where('destination', 'like', '%' . $destination . '%');
        }

        return Inertia::render('ExitPermits/Index', [
            'canCreate' => false,
            'viewerRole' => $user?->role?->code,
            'pageMode' => 'history',
            'filters' => [
                'submitter' => $submitter,
                'requestor' => $requestor,
                'date' => $permitDate,
                'month' => $month >= 1 && $month <= 12 ? (string) $month : '',
                'year' => $year >= 1900 && $year <= 3000 ? (string) $year : '',
                'exit_type' => $exitType,
                'destination' => $destination,
            ],
            'exitTypes' => ExitPermit::EXIT_TYPES,
            'exitPermits' => $query
                ->paginate(10)
                ->withQueryString()
                ->through(fn(ExitPermit $exitPermit) => $this->transformExitPermitListItem($exitPermit, $user)),
        ]);
    }

    private function transformExitPermitListItem(ExitPermit $exitPermit, $user): array
    {
        return [
            'id' => $exitPermit->id,
            'employee_name' => $exitPermit->user?->name,
            'requestor_names' => $exitPermit->requestors
                ->pluck('name')
                ->filter()
                ->values()
                ->all(),
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
        ];
    }

    public function create(): Response
    {
        return Inertia::render('ExitPermits/Create', [
            'exitTypes' => ExitPermit::EXIT_TYPES,
            'carOptions' => $this->carOptions(),
            'driverOptions' => $this->driverOptions(),
            'costCenterOptions' => $this->costCenterOptions(),
            'requestorLookupRouteName' => 'exit-permits.requestor-options',
        ]);
    }

    public function requestorLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $queryText = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);
        $connectionName = (string) config('attendance.requestor_source_connection', config('database.default'));
        $configuredSourceTable = trim((string) config('attendance.requestor_source_table', ''));
        $tableCandidates = collect(array_merge(
            ['karyawan'],
            $configuredSourceTable !== '' ? [$configuredSourceTable] : [],
            (array) config('attendance.requestor_source_tables', []),
            ['employees', 'absensi_karyawan'],
        ))
            ->map(fn($table) => trim((string) $table))
            ->filter(fn(string $table) => $table !== '')
            ->unique()
            ->values();

        $sourceTable = $tableCandidates
            ->first(fn(string $table) => Schema::connection($connectionName)->hasTable($table));

        if (!$sourceTable) {
            return response()->json(['items' => []]);
        }

        $columns = collect(Schema::connection($connectionName)->getColumnListing($sourceTable))
            ->map(fn($column) => strtolower((string) $column))
            ->values();

        $findColumn = function (array $candidates) use ($columns): ?string {
            foreach ($candidates as $candidate) {
                if ($columns->contains(strtolower($candidate))) {
                    return (string) $columns->first(fn($column) => strtolower((string) $column) === strtolower($candidate));
                }
            }

            return null;
        };

        $nameColumn = $findColumn(['name', 'nama', 'employee_name', 'full_name']);
        $employeeIdColumn = $findColumn(['employee_id', 'id_employee', 'nik', 'pin', 'emp_id']);
        $positionColumn = $findColumn(['position', 'posisi', 'jabatan', 'job_title']);
        $departmentColumn = $findColumn(['department', 'departemen', 'dept', 'department_name', 'bagian']);

        if (!$nameColumn && !$employeeIdColumn) {
            return response()->json(['items' => []]);
        }

        $query = DB::connection($connectionName)->table($sourceTable);

        if ($queryText !== '') {
            $query->where(function ($builder) use ($queryText, $nameColumn, $employeeIdColumn, $positionColumn, $departmentColumn) {
                foreach (array_filter([$nameColumn, $employeeIdColumn, $positionColumn, $departmentColumn]) as $column) {
                    $builder->orWhere($column, 'like', '%' . $queryText . '%');
                }
            });
        }

        $selects = [];
        $selects[] = $nameColumn ? ($nameColumn . ' as name') : DB::raw('NULL as name');
        $selects[] = $employeeIdColumn ? ($employeeIdColumn . ' as employee_id') : DB::raw('NULL as employee_id');
        $selects[] = $positionColumn ? ($positionColumn . ' as position') : DB::raw('NULL as position');
        $selects[] = $departmentColumn ? ($departmentColumn . ' as department') : DB::raw('NULL as department');

        $items = $query
            ->select($selects)
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'name' => trim((string) ($row->name ?? '')),
                'employee_id' => trim((string) ($row->employee_id ?? '')),
                'position' => trim((string) ($row->position ?? '')),
                'department' => trim((string) ($row->department ?? '')),
            ])
            ->filter(fn(array $row) => $row['name'] !== '' || $row['employee_id'] !== '')
            ->unique(fn(array $row) => strtolower($row['employee_id'] . '|' . $row['name']))
            ->values()
            ->all();

        return response()->json(['items' => $items]);
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
            'costCenter:id,name',
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
                'plan_check_in' => $exitPermit->plan_check_in,
                'destination' => $exitPermit->destination,
                'exit_type' => $exitPermit->exit_type,
                'order_car' => (bool) $exitPermit->order_car,
                'cost_center_name' => $exitPermit->costCenter?->name,
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

        if (
            $orderCar && in_array($exitPermit->exit_type, [
                ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
                ExitPermit::EXIT_TYPE_ASSIGNMENT,
                ExitPermit::EXIT_TYPE_COMPANY,
            ], true)
        ) {
            $ratna = User::query()
                ->where('email', self::CAR_DRIVER_COORDINATOR_EMAIL)
                ->first();

            if ($ratna) {
                $ratna->notify(new ArrangeCarDriverRequested($exitPermit));
            }
        }

        // Notify managers that a new exit permit needs approval
        $managers = User::query()
            ->whereHas('role', fn($q) => $q->where('code', 'manager'))
            ->where('is_available_for_approval', true)
            ->get();

        foreach ($managers as $manager) {
            $this->notifyExitPermitApproverOnce($manager, $exitPermit, 'manager');
        }

        return redirect()->route('exit-permits.index')
            ->with('success', 'Exit permit data has been successfully created.');
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
                'cost_center_id' => $exitPermit->cost_center_id,
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
            'costCenterOptions' => $this->costCenterOptions(),
            'requestorLookupRouteName' => 'exit-permits.requestor-options',
            'attendancePreview' => $request->session()->get($this->attendancePreviewSessionKey($exitPermit->id)),
        ]);
    }

    /*
    public function previewAttendance(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $this->authorizeUser($exitPermit);

        if (!$this->canVerifyAttendance($exitPermit, $request->user())) {
            abort(403);
        }

        $this->validateAttendanceVerificationData($request, $exitPermit);

        if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Attendance matching preview is only applicable for company/business trip type.',
            ]);
        }

        $preview = $this->makeAttendancePreview(
            $exitPermit,
            $request->file('attendance_file'),
            now()->toDateString(),
        );
        $request->session()->put($this->attendancePreviewSessionKey($exitPermit->id), $preview);

        $this->logAttendanceImport(
            $exitPermit,
            $request->user()?->id,
            $preview,
            'manual_preview',
        );

        return redirect()->route('exit-permits.edit', $exitPermit)
            ->with('success', 'Attendance matching preview has been created. Please review before saving verification.');
    }
    */

    public function update(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $this->authorizeUser($exitPermit);

        $user = $request->user();
        $roleCode = $user?->role?->code;
        $isOwner = $exitPermit->user_id === $user?->id;
        $canApprove = $this->canApprove($user);
        $canArrangeCar = $this->canArrangeCar($exitPermit, $user);
        $canVerifyAttendance = $this->canVerifyAttendance($exitPermit, $user);
        $attachmentPhoto = $request->file('attachment_photo');
        $validated = [];
        $shouldNotifyOrderCar = false;
        $shouldNotifyReimbursement = false;
        $shouldNotifyMealCompleted = false;
        $postMdPathBefore = $exitPermit->post_md_path;

        if ($isOwner && !$canApprove && !$canArrangeCar && !$canVerifyAttendance) {
            $wasOrderCar = (bool) $exitPermit->order_car;
            $validated = $this->validatedData($request);
            unset($validated['attachment_photo']);

            if ($exitPermit->manager_approved_at || $exitPermit->md_approved_at || $exitPermit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Exit permit that has been processed for approval cannot be modified by the requester.',
                ]);
            }

            $exitPermit->fill($validated);
            $exitPermit->syncBusinessRules();
            $this->syncRequestorItems($exitPermit, $validated['requestor_items'] ?? []);

            $shouldNotifyOrderCar = !$wasOrderCar && (bool) $exitPermit->order_car;

            if ($attachmentPhoto) {
                $this->replaceAttachment($exitPermit, $attachmentPhoto);
            }
        }

        if ($canApprove) {
            $approval = $this->validateApprovalData($request);
            $newStatus = $approval['status'];
            $isDualApprovalUser = (bool) ($user?->isWidaMustikaSari() ?? false);

            if ($roleCode === 'manager' && !($isDualApprovalUser && $exitPermit->manager_approved_at && $exitPermit->md_approved_at && !$exitPermit->hr_verified_at)) {
                if ($exitPermit->manager_approved_at || $exitPermit->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'status' => 'Manager approval has already been processed.',
                    ]);
                }

                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $this->fillApprovalData($exitPermit, 'manager');

                    if ($newStatus === 'approved') {
                        $hrApproverId = $this->resolveHrApproverId();

                        if (!$hrApproverId) {
                            throw ValidationException::withMessages([
                                'status' => 'Tiered HR PIC not found. Please contact the Administrator.',
                            ]);
                        }

                        $exitPermit->hr_approver_id = $hrApproverId;
                        $exitPermit->status = 'pending';
                        // Notify the assigned HR Manager
                        $hrUser = User::query()->find($hrApproverId);
                        if ($hrUser) {
                            $this->notifyExitPermitApproverOnce($hrUser, $exitPermit, 'hr_manager');
                        }

                        // Notify MDs that manager has approved and MD action is required
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

                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $this->fillApprovalData($exitPermit, 'md');

                    if ($newStatus === 'approved') {
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

                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $exitPermit->hr_verified_by = $user?->id;
                    $exitPermit->hr_verified_at = now();
                    $exitPermit->status = $newStatus;

                    if ($newStatus === 'approved') {
                        $exitPermit->attendance_checked_by = null;
                        $exitPermit->attendance_checked_at = null;
                        $exitPermit->has_valid_checkin = null;
                        $exitPermit->post_md_path = null;
                        // Notify attendance verifier (Sisca) that HR Manager approved
                        $sisca = User::query()
                            ->where('email', self::ATTENDANCE_VERIFIER_EMAIL)
                            ->first();

                        if ($sisca) {
                            $sisca->notify(new ExitPermitApprovalRequested($exitPermit, 'attendance_verifier'));
                        }

                        $this->notifyExitPermitOwner($exitPermit, 'hr_manager_approved');
                    }
                }
            }

        }

        if (!$canApprove && !$canArrangeCar && $canVerifyAttendance) {
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
                        'attendance_file' => 'Please preview attendance first or upload an attendance file before saving.',
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

            if (
                $postMdPathBefore !== ExitPermit::POST_MD_PATH_REIMBURSEMENT
                && $exitPermit->post_md_path === ExitPermit::POST_MD_PATH_REIMBURSEMENT
            ) {
                $shouldNotifyReimbursement = true;
            }

            if (
                $postMdPathBefore !== ExitPermit::POST_MD_PATH_MEAL
                && $exitPermit->post_md_path === ExitPermit::POST_MD_PATH_MEAL
            ) {
                $shouldNotifyMealCompleted = true;
            }
        }

        if (!$canApprove && $canArrangeCar) {
            $arrangementData = $this->validateVehicleArrangementData($request, $exitPermit);

            $selectedCar = Car::query()->find((int) $arrangementData['car_id']);
            $selectedDriver = Driver::query()->find((int) $arrangementData['driver_id']);

            $exitPermit->vehicle_plate = strtoupper((string) ($selectedCar?->police_no ?? ''));
            $exitPermit->driver_name = (string) ($selectedDriver?->name ?? '');
        }

        if (!$isOwner && !$canApprove && !$canArrangeCar && !$canVerifyAttendance) {
            throw ValidationException::withMessages([
                'status' => 'You do not have access to modify this data.',
            ]);
        }

        $exitPermit->save();

        if ($canVerifyAttendance) {
            $this->exitPermitLunchConversionService->applyIfEligible($exitPermit->fresh(['requestors', 'user']));
        }

        if (
            $shouldNotifyOrderCar && in_array($exitPermit->exit_type, [
                ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
                ExitPermit::EXIT_TYPE_ASSIGNMENT,
                ExitPermit::EXIT_TYPE_COMPANY,
            ], true)
        ) {
            $ratna = User::query()
                ->where('email', self::CAR_DRIVER_COORDINATOR_EMAIL)
                ->first();

            if ($ratna) {
                $ratna->notify(new ArrangeCarDriverRequested($exitPermit));
            }
        }

        if ($shouldNotifyReimbursement) {
            $this->notifyReimbursementRequired($exitPermit);
        }

        if ($shouldNotifyMealCompleted) {
            $this->notifyExitPermitOwner($exitPermit, 'completed_meal');
        }

        $redirectRoute = ($canApprove || $canArrangeCar || $canVerifyAttendance)
            ? 'exit-permit-approvals.index'
            : 'exit-permits.index';

        return redirect()->route($redirectRoute)->with('success', 'Exit permit data has been successfully updated.');
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

    private function notifyExitPermitApproverOnce($approver, ExitPermit $exitPermit, string $stage): void
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

    private function notifyReimbursementRequired(ExitPermit $exitPermit): void
    {
        $exitPermit->loadMissing('user:id,name,email');
        $owner = $exitPermit->user;

        if (!$owner || !filled($owner->email)) {
            return;
        }

        $owner->notify(new ReimbursementSubmissionRequested($exitPermit));
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

        return redirect()->route('exit-permits.index')->with('success', 'Exit permit data has been successfully deleted.');
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

    public function print(ExitPermit $exitPermit)
    {
        $this->authorizeView($exitPermit);

        $exitPermit->load([
            'user:id,name',
            'requestors',
        ]);

        return Pdf::loadView('pdf.exit-permit', [
            'exitPermit' => $exitPermit,
        ])
            ->setPaper('a4', 'portrait')
            ->stream('exit-permit-' . $exitPermit->id . '.pdf');
    }

    private function canApprove($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md', 'hr_manager', 'admin'], true);
    }

    private function canViewAllData($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md', 'hr_manager', 'hr', 'admin'], true);
    }

    private function canAccessApprovalMenu($user): bool
    {
        if (in_array($user?->role?->code, ['manager', 'md', 'hr_manager', 'admin'], true)) {
            return true;
        }

        return $user?->role?->code === 'hr'
            && in_array(strtolower((string) $user?->email), [
                self::ATTENDANCE_VERIFIER_EMAIL,
                self::CAR_DRIVER_COORDINATOR_EMAIL,
            ], true);
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
        if (
            $user?->isWidaMustikaSari()
            && $exitPermit->status === 'pending'
            && $exitPermit->manager_approved_at
            && $exitPermit->md_approved_at
            && !$exitPermit->hr_verified_at
        ) {
            return true;
        }

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

        if ($user?->role?->code === 'admin') {
            return $exitPermit->status === 'pending'
                && (
                    !$exitPermit->manager_approved_at
                    || !$exitPermit->md_approved_at
                    || !$exitPermit->hr_verified_at
                );
        }

        return false;
    }

    private function approvalStageLabel(ExitPermit $exitPermit): string
    {
        $approverName = $exitPermit->hrApprover?->name ?? 'HR Approver';

        if ($exitPermit->status === 'approved') {
            if ($exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP) {
                // return 'Approved by HR Manager | Acknowledged by Sisca (HRD)';
                return 'Approved by HR Manager';
            }

            if (!$exitPermit->attendance_checked_at) {
                return 'Approved by HR Manager | Waiting for Sisca attendance verification';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_MEAL) {
                return 'Approved by HR Manager | Acknowledged by Sisca (HRD)';
            }

            if ($exitPermit->post_md_path === ExitPermit::POST_MD_PATH_REIMBURSEMENT) {
                if ($exitPermit->attendance_checked_at && !$exitPermit->has_valid_checkin) {
                    return 'Approved by HR Manager | Matching Attendance Dont Match | Reimbursement Path';
                }

                return 'Approved by HR Manager | Reimbursement Path';
            }

            if ($exitPermit->attendance_checked_at && !$exitPermit->has_valid_checkin) {
                return 'Approved by HR Manager | Matching Attendance Dont Match';
            }

            return 'Approved by HR Manager | Acknowledged by Sisca (HRD)';
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
        if ($exitPermit->status === 'approved' && (bool) $exitPermit->attendance_checked_at && $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP && !(bool) $exitPermit->has_valid_checkin) {
            return 'Matching Attendance Dont Match';
        }

        if ($exitPermit->status === 'approved' && (bool) $exitPermit->attendance_checked_at) {
            return 'Acknowledged by Sisca (HRD)';
        }

        return strtoupper((string) $exitPermit->status);
    }

    private function validatedData(Request $request, bool $allowStatus = false): array
    {
        $validated = $request->validate([
            'permit_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after_or_equal:start_time'],
            'plan_check_in' => ['nullable', 'boolean'],
            'destination' => ['nullable', 'string', 'max:255', 'required_if:exit_type,business_trip,assignment,company'],
            'exit_type' => ['required', Rule::in(ExitPermit::EXIT_TYPES)],
            'requestor_items' => ['required', 'array', 'min:1'],
            'requestor_items.*.name' => ['required', 'string', 'max:120'],
            'requestor_items.*.employee_id' => ['nullable', 'string', 'max:60'],
            'requestor_items.*.position' => ['nullable', 'string', 'max:120'],
            'requestor_items.*.department' => ['nullable', 'string', 'max:120'],
            'requestor_items.*.reimburs_lunch_box' => ['nullable', 'string', 'max:10'],
            'order_car' => ['nullable', 'boolean'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
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
        $planCheckInInput = $request->input('plan_check_in');
        $validated['plan_check_in'] = $planCheckInInput === null ? null : $request->boolean('plan_check_in');

        if (in_array($validated['exit_type'], [ExitPermit::EXIT_TYPE_SICK, ExitPermit::EXIT_TYPE_PERSONAL], true)) {
            $validated['destination'] = null;
        }

        if (
            !in_array($validated['exit_type'], [
                ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
                ExitPermit::EXIT_TYPE_ASSIGNMENT,
                ExitPermit::EXIT_TYPE_COMPANY,
            ], true)
        ) {
            $validated['cost_center_id'] = null;
        }

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

        $startTime = Carbon::createFromFormat('H:i', (string) $validated['start_time']);
        $eligibleForLunch = $startTime->lte(Carbon::createFromTimeString('13:00'));

        $validated['requestor_items'] = collect($validated['requestor_items'] ?? [])
            ->map(fn(array $item, int $index) => [
                'row_number' => $index + 1,
                'name' => (string) ($item['name'] ?? ''),
                'employee_id' => filled($item['employee_id'] ?? null) ? (string) $item['employee_id'] : null,
                'position' => filled($item['position'] ?? null) ? (string) $item['position'] : null,
                'department' => filled($item['department'] ?? null) ? (string) $item['department'] : null,
                'reimburs_lunch_box' => $eligibleForLunch ? 'Y' : 'N',
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
                'vehicle_plate' => 'Car/driver arrangement is only applicable for business trip type.',
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

    private function costCenterOptions(): array
    {
        return CostCenter::query()
            ->orderBy('name')
            ->get(['id', 'name', 'cost_center_sap', 'desc_cost_c'])
            ->map(fn(CostCenter $costCenter) => [
                'id' => $costCenter->id,
                'name' => $costCenter->name,
                'cost_center_sap' => $costCenter->cost_center_sap,
                'desc_cost_c' => $costCenter->desc_cost_c,
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
                'status' => 'Attendance verification can only be done after HR Manager approval.',
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

    private function makeAttendancePreview(ExitPermit $exitPermit, ?UploadedFile $uploadedFile = null, ?string $matchDate = null): array
    {
        $sourcePayload = $this->attendanceMatchingService->loadRowsWithSource($uploadedFile);

        return $this->attendanceMatchingService->buildPreview(
            $exitPermit,
            $sourcePayload['rows'],
            $sourcePayload['source'],
            $matchDate,
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
                'attendance_file' => 'Attendance file must be uploaded or ATTENDANCE_SOURCE_PATH must be set first.',
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
                'attendance_file' => 'No attendance file (csv/xlsx) found in the shared folder.',
            ]);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'attendance_');

        if ($tempPath === false) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Failed to prepare temporary attendance file.',
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
                'attendance_file' => 'Attendance file format is not supported. Please use CSV or XLSX.',
            ]),
        };

        if (count($rawRows) < 2) {
            throw ValidationException::withMessages([
                'attendance_file' => 'Attendance file is empty or does not contain header and data rows.',
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
                'attendance_file' => 'XLSX file could not be opened.',
            ]);
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStringXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if (!$sheetXml) {
            throw ValidationException::withMessages([
                'attendance_file' => 'XLSX worksheet not found.',
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
                'attendance_file' => 'Invalid XLSX sheet format.',
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
        $isDualApprovalUser = (bool) ($user?->isWidaMustikaSari() ?? false);

        if ($exitPermit->user_id === $user?->id) {
            return;
        }

        if ($isDualApprovalUser) {
            $canApproveAsManager = $exitPermit->status === 'pending' && !$exitPermit->manager_approved_at;
            $canApproveAsHrManager = $exitPermit->status === 'pending'
                && (bool) $exitPermit->manager_approved_at
                && (bool) $exitPermit->md_approved_at
                && !$exitPermit->hr_verified_at;

            if ($canApproveAsManager || $canApproveAsHrManager) {
                return;
            }

            abort(403);
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
        if ($user?->role?->code === 'admin') {
            return $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
                && (bool) $exitPermit->order_car
                && $exitPermit->status === 'pending';
        }

        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::CAR_DRIVER_COORDINATOR_EMAIL
            && $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            && (bool) $exitPermit->order_car
            && $exitPermit->status === 'pending';
    }

    private function canVerifyAttendance(ExitPermit $exitPermit, $user): bool
    {
        if ($user?->role?->code === 'admin') {
            return $exitPermit->exit_type === ExitPermit::EXIT_TYPE_BUSINESS_TRIP
                && $exitPermit->status === 'approved'
                && (bool) $exitPermit->md_approved_at
                && (bool) $exitPermit->hr_verified_at;
        }

        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::ATTENDANCE_VERIFIER_EMAIL
            && $exitPermit->status === 'approved'
            && (bool) $exitPermit->md_approved_at
            && (bool) $exitPermit->hr_verified_at
            && !$exitPermit->attendance_checked_at;
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
