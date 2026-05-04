<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use App\Models\OrderMeal;
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
    public function index(): Response
    {
        return $this->indexByScope(OrderMeal::SCOPE_GENERAL);
    }

    public function indexExitPermit(): Response
    {
        return $this->indexByScope(OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function create(): Response
    {
        return $this->createByScope(OrderMeal::SCOPE_GENERAL);
    }

    public function createExitPermit(): Response
    {
        return $this->createByScope(OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeByScope($request, OrderMeal::SCOPE_GENERAL);
    }

    public function storeExitPermit(Request $request): RedirectResponse
    {
        return $this->storeByScope($request, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function edit(OrderMeal $orderMeal): Response
    {
        return $this->editByScope($orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function editExitPermit(OrderMeal $orderMeal): Response
    {
        return $this->editByScope($orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function update(Request $request, OrderMeal $orderMeal): RedirectResponse
    {
        return $this->updateByScope($request, $orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function updateExitPermit(Request $request, OrderMeal $orderMeal): RedirectResponse
    {
        return $this->updateByScope($request, $orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    public function destroy(OrderMeal $orderMeal): RedirectResponse
    {
        return $this->destroyByScope($orderMeal, OrderMeal::SCOPE_GENERAL);
    }

    public function destroyExitPermit(OrderMeal $orderMeal): RedirectResponse
    {
        return $this->destroyByScope($orderMeal, OrderMeal::SCOPE_EXIT_PERMIT);
    }

    private function indexByScope(string $scope): Response
    {
        $user = request()->user();
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
        return Inertia::render('OrderMeals/Create', [
            'mode' => $scope,
            'indexRouteName' => $this->routeName($scope, 'index'),
            'storeRouteName' => $this->routeName($scope, 'store'),
        ]);
    }

    private function storeByScope(Request $request, string $scope): RedirectResponse
    {
        $validated = $this->validatedData($request, false, true);
        $scheduleType = $validated['schedule_type'];
        $repeatCount = (int) ($validated['repeat_count'] ?? 1);
        $baseMealDate = Carbon::parse((string) $validated['meal_date']);
        $exitPermitId = null;

        if ($scope === OrderMeal::SCOPE_EXIT_PERMIT) {
            $exitPermitId = $this->resolveVerifiedExitPermitIdForMeal($request->user()->id, $baseMealDate->toDateString());
        }

        $baseQuantity = (int) $validated['quantity'];
        $visitorCount = (int) $validated['visitor_count'];
        $totalQuantity = $baseQuantity + $visitorCount;
        $actualQuantity = $repeatCount > 1 ? 0 : (int) $validated['actual_quantity'];

        if ($actualQuantity > $totalQuantity) {
            throw ValidationException::withMessages([
                'actual_quantity' => 'Realisasi makan tidak boleh melebihi total paket (paket dasar + visitor).',
            ]);
        }

        for ($i = 0; $i < $repeatCount; $i++) {
            $mealDate = match ($scheduleType) {
                'daily' => (clone $baseMealDate)->addDays($i),
                'weekly' => (clone $baseMealDate)->addWeeks($i),
                default => (clone $baseMealDate),
            };

            OrderMeal::create([
                'user_id' => $request->user()->id,
                'order_scope' => $scope,
                'exit_permit_id' => $scope === OrderMeal::SCOPE_EXIT_PERMIT ? $exitPermitId : null,
                'meal_date' => $mealDate->toDateString(),
                'meal_type' => 'lunch',
                'menu_name' => $validated['menu_name'],
                'quantity' => $totalQuantity,
                'actual_quantity' => $actualQuantity,
                'visitor_count' => $visitorCount,
                'schedule_type' => $scheduleType,
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]);
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

    private function validatedData(Request $request, bool $allowStatus = false, bool $isStore = false): array
    {
        $validated = $request->validate([
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

        return $validated;
    }

    private function resolveVerifiedExitPermitIdForMeal(int $userId, string $mealDate): int
    {
        $exitPermit = ExitPermit::query()
            ->where('user_id', $userId)
            ->whereDate('permit_date', $mealDate)
            ->where('status', 'approved')
            ->whereNotNull('md_approved_at')
            ->whereNotNull('attendance_checked_at')
            ->where('has_valid_checkin', true)
            ->where('returned_to_office', true)
            ->where('post_md_path', ExitPermit::POST_MD_PATH_MEAL)
            ->latest('id')
            ->first();

        if ($exitPermit) {
            return $exitPermit->id;
        }

        throw ValidationException::withMessages([
            'meal_date' => 'Order meal hanya bisa diajukan setelah exit permit di-approve MD, diverifikasi absensi oleh Sisca, dan karyawan kembali ke kantor.',
        ]);
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
