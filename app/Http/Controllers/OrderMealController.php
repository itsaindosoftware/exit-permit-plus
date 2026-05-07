<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
use App\Services\ExitPermitLunchConversionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderMealController extends Controller
{
    private const ATTENDANCE_VERIFIER_EMAIL = 'sisca.dewiyani@example.com';

    public function __construct(private readonly ExitPermitLunchConversionService $exitPermitLunchConversionService)
    {
    }

    public function index(): Response
    {
        $this->ensureSisca(request()->user());
        return $this->indexByScope(OrderMeal::SCOPE_GENERAL);
    }

    public function indexExitPermit(): Response
    {
        $this->ensureSisca(request()->user());
        return $this->indexByScope(OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function create(): Response
    {
        $this->ensureSisca(request()->user());
        return $this->createByScope(OrderMeal::SCOPE_GENERAL);
    }

    public function createExitPermit(): Response|RedirectResponse
    {
        $this->ensureSisca(request()->user());
        return $this->createByScope(OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSisca($request->user());
        return $this->storeByScope($request, OrderMeal::SCOPE_GENERAL);
    }

    public function storeExitPermit(Request $request): RedirectResponse
    {
        $this->ensureSisca($request->user());
        return $this->storeByScope($request, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function edit(OrderMeal $orderMeal): Response
    {
        $this->ensureSisca(request()->user());
        return $this->editByScope($orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function show(OrderMeal $orderMeal): Response
    {
        $this->ensureSisca(request()->user());
        return $this->showByScope($orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function editExitPermit(OrderMeal $orderMeal): Response
    {
        $this->ensureSisca(request()->user());
        return $this->editByScope($orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function showExitPermit(OrderMeal $orderMeal): Response
    {
        $this->ensureSisca(request()->user());
        return $this->showByScope($orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function update(Request $request, OrderMeal $orderMeal): RedirectResponse
    {
        $this->ensureSisca($request->user());
        return $this->updateByScope($request, $orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function updateExitPermit(Request $request, OrderMeal $orderMeal): RedirectResponse
    {
        $this->ensureSisca($request->user());
        return $this->updateByScope($request, $orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function destroy(OrderMeal $orderMeal): RedirectResponse
    {
        $this->ensureSisca(request()->user());
        return $this->destroyByScope($orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function destroyExitPermit(OrderMeal $orderMeal): RedirectResponse
    {
        $this->ensureSisca(request()->user());
        return $this->destroyByScope($orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    private function indexByScope(string $scope): Response
    {
        $user = request()->user();

        if ($scope === OrderMeal::SCOPE_GENERAL) {
            $this->exitPermitLunchConversionService->applyPendingForDate(now()->toDateString());
        }

        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT) {
            $this->ensureSisca($user);
        }
        $query = OrderMeal::query()
            ->with('user:id,name')
            ->where('meal_type', 'lunch')
            ->where('order_scope', $scope)
            ->orderByDesc('meal_date')
            ->orderByDesc('id');

        if (!$this->canApprove($user)) {
            $query->where('user_id', $user->id);
        }

        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(quantity), 0) as provided_total, COALESCE(SUM(actual_quantity), 0) as actual_total')
            ->first();

        $dailyAggregation = (clone $query)
            ->reorder()
            ->selectRaw('meal_date, COALESCE(SUM(quantity), 0) as provided_total, COALESCE(SUM(actual_quantity), 0) as actual_total')
            ->groupBy('meal_date')
            ->orderByDesc('meal_date')
            ->limit(120)
            ->get();

        return Inertia::render('OrderMeals/Index', [
            'mode' => $scope,
            'indexRouteName' => $this->routeName($scope, 'index'),
            'createRouteName' => $this->routeName($scope, 'create'),
            'showRouteName' => $this->routeName($scope, 'show'),
            'editRouteName' => $this->routeName($scope, 'edit'),
            'destroyRouteName' => $this->routeName($scope, 'destroy'),
            'orderMeals' => $query
                ->paginate(10)
                ->through(fn(OrderMeal $orderMeal) => [
                    'id' => $orderMeal->id,
                    'employee_name' => $orderMeal->user?->name,
                    'meal_date' => $orderMeal->meal_date ? (string) $orderMeal->meal_date : null,
                    'meal_type' => $orderMeal->meal_type,
                    'menu_name' => $orderMeal->menu_name,
                    'quantity' => $orderMeal->quantity,
                    'actual_quantity' => $orderMeal->actual_quantity,
                    'visitor_count' => $orderMeal->visitor_count,
                    'schedule_type' => $orderMeal->schedule_type,
                    'remaining_quantity' => $orderMeal->remaining_quantity,
                    'status' => $orderMeal->status,
                ]),
            'summary' => [
                'provided_total' => (int) ($summary->provided_total ?? 0),
                'actual_total' => (int) ($summary->actual_total ?? 0),
                'remaining_total' => max(0, (int) ($summary->provided_total ?? 0) - (int) ($summary->actual_total ?? 0)),
            ],
            'notEatenCharts' => [
                'daily' => $this->dailyNotEatenTrend($dailyAggregation),
                'weekly' => $this->weeklyNotEatenTrend($dailyAggregation),
                'monthly' => $this->monthlyNotEatenTrend($dailyAggregation),
            ],
        ]);
    }

    private function createByScope(string $scope): Response
    {
        $eligibleExitPermits = [];
        $eligibilityWarning = null;

        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT) {
            $this->ensureSisca(request()->user());
            $eligibleExitPermits = $this->eligibleExitPermitsForMeal(request()->user());

            if (count($eligibleExitPermits) === 0) {
                $eligibilityWarning = 'Order meal Exit Permit tersedia setelah Exit Permit berstatus Checked By HR Sisca, requestor hasil matching Y, dan kembali ke kantor.';
            }
        }

        return Inertia::render('OrderMeals/Create', [
            'mode' => $scope,
            'indexRouteName' => $this->routeName($scope, 'index'),
            'storeRouteName' => $this->routeName($scope, 'store'),
            'eligibleExitPermits' => $eligibleExitPermits,
            'eligibilityWarning' => $eligibilityWarning,
        ]);
    }

    private function showByScope(OrderMeal $orderMeal, string $scope): Response
    {
        if ($orderMeal->order_scope !== $scope) {
            abort(404);
        }

        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT) {
            $this->ensureSisca(request()->user());
        }

        $this->authorizeUser($orderMeal);

        $orderMeal->load([
            'user:id,name,email',
            'exitPermit:id,user_id,permit_date,destination,attendance_checked_at,has_valid_checkin,returned_to_office',
            'exitPermit.user:id,name,email',
            'exitPermit.requestors:id,exit_permit_id,row_number,name,employee_id,position,department,reimburs_lunch_box',
        ]);

        return Inertia::render('OrderMeals/Show', [
            'mode' => $scope,
            'indexRouteName' => $this->routeName($scope, 'index'),
            'editRouteName' => $this->routeName($scope, 'edit'),
            'orderMeal' => [
                'id' => $orderMeal->id,
                'employee_name' => $orderMeal->user?->name,
                'employee_email' => $orderMeal->user?->email,
                'meal_date' => $orderMeal->meal_date ? (string) $orderMeal->meal_date : null,
                'meal_type' => $orderMeal->meal_type,
                'menu_name' => $orderMeal->menu_name,
                'quantity' => $orderMeal->quantity,
                'actual_quantity' => $orderMeal->actual_quantity,
                'visitor_count' => $orderMeal->visitor_count,
                'remaining_quantity' => $orderMeal->remaining_quantity,
                'schedule_type' => $orderMeal->schedule_type,
                'notes' => $orderMeal->notes,
                'status' => $orderMeal->status,
            ],
            'exitPermit' => $orderMeal->exitPermit ? [
                'id' => $orderMeal->exitPermit->id,
                'permit_date' => $orderMeal->exitPermit->permit_date ? (string) $orderMeal->exitPermit->permit_date : null,
                'destination' => $orderMeal->exitPermit->destination,
                'has_valid_checkin' => (bool) $orderMeal->exitPermit->has_valid_checkin,
                'returned_to_office' => (bool) $orderMeal->exitPermit->returned_to_office,
                'attendance_checked_at' => optional($orderMeal->exitPermit->attendance_checked_at)?->toDateTimeString(),
                'owner_name' => $orderMeal->exitPermit->user?->name,
                'owner_email' => $orderMeal->exitPermit->user?->email,
                'requestors' => $orderMeal->exitPermit->requestors
                    ->map(fn($requestor) => [
                        'row_number' => $requestor->row_number,
                        'name' => $requestor->name,
                        'employee_id' => $requestor->employee_id,
                        'position' => $requestor->position,
                        'department' => $requestor->department,
                        'reimburs_lunch_box' => $requestor->reimburs_lunch_box,
                    ])
                    ->values()
                    ->all(),
            ] : null,
        ]);
    }

    private function storeByScope(Request $request, string $scope): RedirectResponse
    {
        $validated = $this->validatedData($request, false, true, $scope);
        $scheduleType = $validated['schedule_type'];
        $repeatCount = (int) ($validated['repeat_count'] ?? 1);
        $baseMealDate = Carbon::parse((string) $validated['meal_date']);
        $exitPermitId = null;
        $matchedRequestorCount = null;

        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT) {
            $this->ensureSisca($request->user());
            $selectedExitPermitId = (int) ($validated['exit_permit_id'] ?? 0);
            $exitPermit = $this->resolveVerifiedExitPermitForMeal($request->user(), $selectedExitPermitId, $baseMealDate->toDateString());
            $exitPermitId = $exitPermit->id;
            $matchedRequestorCount = $exitPermit->requestors()
                ->whereRaw("UPPER(TRIM(COALESCE(reimburs_lunch_box, 'N'))) = ?", ['Y'])
                ->count();
        }

        $baseQuantity = (int) $validated['quantity'];

        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT && $matchedRequestorCount !== null) {
            $baseQuantity = max(1, (int) $matchedRequestorCount);
        }

        $visitorCount = (int) $validated['visitor_count'];
        $totalQuantity = $baseQuantity + $visitorCount;
        $actualQuantity = $repeatCount > 1 ? 0 : (int) $validated['actual_quantity'];

        if ($actualQuantity > $totalQuantity) {
            throw ValidationException::withMessages([
                'actual_quantity' => 'Realisasi makan tidak boleh melebihi total paket (paket dasar + visitor).',
            ]);
        }

        $appliedMealDates = [];

        for ($i = 0; $i < $repeatCount; $i++) {
            $mealDate = match ($scheduleType) {
                'daily' => (clone $baseMealDate)->addDays($i),
                'weekly' => (clone $baseMealDate)->addWeeks($i),
                default => (clone $baseMealDate),
            };

            $mealDateString = $mealDate->toDateString();

            OrderMeal::create([
                'user_id' => $request->user()->id,
                'order_scope' => $scope,
                'exit_permit_id' => $scope === OrderMeal::SCOPE_EXIT_PERMIT ? $exitPermitId : null,
                'meal_date' => $mealDateString,
                'meal_type' => 'lunch',
                'menu_name' => $validated['menu_name'],
                'quantity' => $totalQuantity,
                'actual_quantity' => $actualQuantity,
                'visitor_count' => $visitorCount,
                'schedule_type' => $scheduleType,
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]);

            $appliedMealDates[$mealDateString] = true;
        }

        if ($scope === OrderMeal::SCOPE_GENERAL) {
            foreach (array_keys($appliedMealDates) as $mealDate) {
                $this->exitPermitLunchConversionService->applyPendingForDate((string) $mealDate);
            }
        }

        $successMessage = $repeatCount > 1
            ? "Data order meal berhasil ditambahkan ({$repeatCount} jadwal)."
            : 'Data order meal berhasil ditambahkan.';

        return redirect()->route($this->routeName($scope, 'index'))->with('success', $successMessage);
    }

    private function editByScope(OrderMeal $orderMeal, string $scope): Response
    {
        if ($orderMeal->order_scope !== $scope) {
            abort(404);
        }

        $this->authorizeUser($orderMeal);

        return Inertia::render('OrderMeals/Edit', [
            'mode' => $scope,
            'indexRouteName' => $this->routeName($scope, 'index'),
            'updateRouteName' => $this->routeName($scope, 'update'),
            'orderMeal' => [
                'id' => $orderMeal->id,
                'meal_date' => $orderMeal->meal_date ? (string) $orderMeal->meal_date : null,
                'meal_type' => $orderMeal->meal_type,
                'menu_name' => $orderMeal->menu_name,
                'quantity' => max(1, (int) $orderMeal->quantity - (int) $orderMeal->visitor_count),
                'actual_quantity' => $orderMeal->actual_quantity,
                'visitor_count' => $orderMeal->visitor_count,
                'schedule_type' => $orderMeal->schedule_type,
                'remaining_quantity' => $orderMeal->remaining_quantity,
                'notes' => $orderMeal->notes,
                'status' => $orderMeal->status,
            ],
            'canApprove' => $this->canApprove(request()->user()),
        ]);
    }

    private function updateByScope(Request $request, OrderMeal $orderMeal, string $scope): RedirectResponse
    {
        if ($orderMeal->order_scope !== $scope) {
            abort(404);
        }

        $this->authorizeUser($orderMeal);

        $canApprove = $this->canApprove($request->user());
        $validated = $this->validatedData($request, $canApprove, false);
        $baseQuantity = (int) $validated['quantity'];
        $visitorCount = (int) $validated['visitor_count'];
        $totalQuantity = $baseQuantity + $visitorCount;

        if ((int) $validated['actual_quantity'] > $totalQuantity) {
            throw ValidationException::withMessages([
                'actual_quantity' => 'Realisasi makan tidak boleh melebihi total paket (paket dasar + visitor).',
            ]);
        }

        if (array_key_exists('schedule_type', $validated) && $validated['schedule_type'] === null) {
            unset($validated['schedule_type']);
        }

        $orderMeal->fill([
            ...$validated,
            'quantity' => $totalQuantity,
            'meal_type' => 'lunch',
        ]);

        if ($canApprove && array_key_exists('status', $validated)) {
            $orderMeal->status = $request->string('status')->toString();

            if (in_array($orderMeal->status, ['approved', 'rejected'], true)) {
                $this->fillApprovalData($orderMeal, $request->user()->role?->code);
            }
        }

        $orderMeal->save();

        if ($scope === OrderMeal::SCOPE_GENERAL && $orderMeal->meal_date) {
            $this->exitPermitLunchConversionService->applyPendingForDate((string) $orderMeal->meal_date);
        }

        return redirect()->route($this->routeName($scope, 'index'))->with('success', 'Data order meal berhasil diperbarui.');
    }

    private function destroyByScope(OrderMeal $orderMeal, string $scope): RedirectResponse
    {
        if ($orderMeal->order_scope !== $scope) {
            abort(404);
        }

        $this->authorizeUser($orderMeal);

        $orderMeal->delete();

        return redirect()->route($this->routeName($scope, 'index'))->with('success', 'Data order meal berhasil dihapus.');
    }

    private function canApprove($user): bool
    {
        return in_array($user?->role?->code, ['manager', 'md'], true);
    }

    private function validatedData(Request $request, bool $allowStatus = false, bool $isStore = false, ?string $scope = null): array
    {
        $validated = $request->validate([
            'exit_permit_id' => [
                Rule::requiredIf(fn() => $isStore && $scope === OrderMeal::SCOPE_EXIT_PERMIT),
                'nullable',
                'integer',
                'exists:exit_permits,id',
            ],
            'meal_date' => ['required', 'date'],
            'menu_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'actual_quantity' => ['required', 'integer', 'min:0'],
            'visitor_count' => ['required', 'integer', 'min:0'],
            'schedule_type' => $isStore
                ? ['required', Rule::in(['single', 'daily', 'weekly'])]
                : ['nullable', Rule::in(['single', 'daily', 'weekly'])],
            'repeat_count' => $isStore
                ? ['required', 'integer', 'min:1', 'max:60']
                : ['nullable', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string'],
            'status' => $allowStatus ? ['nullable', Rule::in(['pending', 'approved', 'rejected'])] : ['nullable'],
        ]);

        if ($isStore && $validated['schedule_type'] === 'single') {
            $validated['repeat_count'] = 1;
        }

        if (!$allowStatus) {
            unset($validated['status']);
        }

        if (!$isStore) {
            unset($validated['repeat_count']);
        }

        if ($scope !== OrderMeal::SCOPE_EXIT_PERMIT) {
            unset($validated['exit_permit_id']);
        }

        return $validated;
    }

    private function resolveVerifiedExitPermitForMeal($user, int $exitPermitId, string $mealDate): ExitPermit
    {
        $exitPermit = ExitPermit::query()
            ->whereKey($exitPermitId)
            ->where('status', 'approved')
            ->whereNotNull('md_approved_at')
            ->whereNotNull('hr_verified_at')
            ->whereNotNull('attendance_checked_at')
            ->whereHas('attendanceChecker', function ($query) {
                $query->whereRaw('LOWER(email) = ?', [self::ATTENDANCE_VERIFIER_EMAIL]);
            })
            ->where(function ($query) {
                $query->where('post_md_path', ExitPermit::POST_MD_PATH_MEAL)
                    ->orWhereNull('post_md_path');
            })
            ->where('has_valid_checkin', true)
            ->where('returned_to_office', true)
            ->whereDate('permit_date', $mealDate)
            ->whereHas('requestors', fn($query) => $query->whereRaw("UPPER(TRIM(COALESCE(reimburs_lunch_box, 'N'))) = ?", ['Y']))
            ->first();

        if (!$exitPermit) {
            throw ValidationException::withMessages([
                'exit_permit_id' => 'Order meal hanya bisa diajukan untuk Exit Permit jalur meal yang sudah diverifikasi Sisca dan memiliki requestor dengan hasil matching Y.',
            ]);
        }

        if ($user?->role?->code === 'user' && (int) $exitPermit->user_id !== (int) $user?->id) {
            throw ValidationException::withMessages([
                'exit_permit_id' => 'Exit Permit yang dipilih bukan milik akun Anda.',
            ]);
        }

        $this->ensureSisca($user);

        return $exitPermit;
    }

    private function eligibleExitPermitsForMeal($user): array
    {
        if (!$this->isSisca($user)) {
            return [];
        }

        $query = ExitPermit::query()
            ->where('status', 'approved')
            ->whereNotNull('md_approved_at')
            ->whereNotNull('hr_verified_at')
            ->whereNotNull('attendance_checked_at')
            ->whereHas('attendanceChecker', function ($query) {
                $query->whereRaw('LOWER(email) = ?', [self::ATTENDANCE_VERIFIER_EMAIL]);
            })
            ->where(function ($query) {
                $query->where('post_md_path', ExitPermit::POST_MD_PATH_MEAL)
                    ->orWhereNull('post_md_path');
            })
            ->where('has_valid_checkin', true)
            ->where('returned_to_office', true)
            ->whereHas('requestors', fn($q) => $q->whereRaw("UPPER(TRIM(COALESCE(reimburs_lunch_box, 'N'))) = ?", ['Y']))
            ->latest('permit_date');

        return $query
            ->with([
                'user:id,name,email',
                'requestors:id,exit_permit_id,row_number,name,employee_id,position,department,reimburs_lunch_box',
            ])
            ->get(['id', 'user_id', 'permit_date', 'start_time', 'end_time', 'destination'])
            ->map(fn(ExitPermit $permit) => [
                'id' => $permit->id,
                'label' => sprintf(
                    '#%d | %s | %s-%s | %s',
                    $permit->id,
                    (string) $permit->permit_date,
                    substr((string) $permit->start_time, 0, 5),
                    substr((string) $permit->end_time, 0, 5),
                    (string) $permit->destination,
                ),
                'owner_name' => $permit->user?->name,
                'owner_email' => $permit->user?->email,
                'requestors' => $permit->requestors
                    ->filter(fn($requestor) => strtoupper(trim((string) ($requestor->reimburs_lunch_box ?? 'N'))) === 'Y')
                    ->map(fn($requestor) => [
                        'row_number' => $requestor->row_number,
                        'name' => $requestor->name,
                        'employee_id' => $requestor->employee_id,
                        'position' => $requestor->position,
                        'department' => $requestor->department,
                        'reimburs_lunch_box' => $requestor->reimburs_lunch_box,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function ensureSisca($user): void
    {
        if (!$this->isSisca($user)) {
            abort(403);
        }
    }

    private function isSisca($user): bool
    {
        return $user?->role?->code === 'hr'
            && strtolower((string) $user?->email) === self::ATTENDANCE_VERIFIER_EMAIL;
    }

    private function routeName(string $scope, string $action): string
    {
        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT) {
            return 'exit-permit-meals.' . $action;
        }

        return 'order-meals.' . $action;
    }

    private function fillApprovalData(OrderMeal $orderMeal, ?string $roleCode): void
    {
        if ($roleCode === 'manager') {
            $orderMeal->manager_approved_by = auth()->id();
            $orderMeal->manager_approved_at = now();
        }

        if ($roleCode === 'md') {
            $orderMeal->md_approved_by = auth()->id();
            $orderMeal->md_approved_at = now();
        }
    }

    private function authorizeUser(OrderMeal $orderMeal): void
    {
        $user = request()->user();

        if ($orderMeal->user_id !== $user->id && !$this->canApprove($user)) {
            abort(403);
        }
    }

    private function dailyNotEatenTrend(Collection $dailyAggregation): array
    {
        return $dailyAggregation
            ->take(14)
            ->reverse()
            ->values()
            ->map(function ($row) {
                $provided = (int) ($row->provided_total ?? 0);
                $actual = (int) ($row->actual_total ?? 0);

                return [
                    'label' => (string) $row->meal_date,
                    'remaining' => max(0, $provided - $actual),
                ];
            })
            ->all();
    }

    private function weeklyNotEatenTrend(Collection $dailyAggregation): array
    {
        return $dailyAggregation
            ->map(function ($row) {
                $date = Carbon::parse((string) $row->meal_date);

                return [
                    'group_key' => $date->format('o-\\WW'),
                    'sort_key' => $date->startOfWeek()->format('Y-m-d'),
                    'remaining' => max(0, (int) ($row->provided_total ?? 0) - (int) ($row->actual_total ?? 0)),
                ];
            })
            ->groupBy('group_key')
            ->map(function (Collection $rows, string $groupKey) {
                return [
                    'label' => $groupKey,
                    'sort_key' => $rows->first()['sort_key'],
                    'remaining' => $rows->sum('remaining'),
                ];
            })
            ->sortBy('sort_key')
            ->values()
            ->take(-12)
            ->values()
            ->map(fn(array $row) => [
                'label' => $row['label'],
                'remaining' => (int) $row['remaining'],
            ])
            ->all();
    }

    private function monthlyNotEatenTrend(Collection $dailyAggregation): array
    {
        return $dailyAggregation
            ->map(function ($row) {
                $date = Carbon::parse((string) $row->meal_date);

                return [
                    'group_key' => $date->format('Y-m'),
                    'sort_key' => $date->startOfMonth()->format('Y-m-d'),
                    'remaining' => max(0, (int) ($row->provided_total ?? 0) - (int) ($row->actual_total ?? 0)),
                ];
            })
            ->groupBy('group_key')
            ->map(function (Collection $rows, string $groupKey) {
                return [
                    'label' => $groupKey,
                    'sort_key' => $rows->first()['sort_key'],
                    'remaining' => $rows->sum('remaining'),
                ];
            })
            ->sortBy('sort_key')
            ->values()
            ->take(-12)
            ->values()
            ->map(fn(array $row) => [
                'label' => $row['label'],
                'remaining' => (int) $row['remaining'],
            ])
            ->all();
    }
}
