<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Driver;
use App\Models\ExitPermit;
use App\Models\ScheduleCarArrangementLog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleCarController extends Controller
{
    private const RATNA_EMAIL = 'ratna@example.com';

    public function index(Request $request): Response
    {
        $this->authorizeRatnaOnly();

        $view = (string) $request->query('view', 'week');
        if (!in_array($view, ['day', 'week', 'month'], true)) {
            $view = 'week';
        }

        $focusDate = $this->resolveDate((string) $request->query('date', now()->toDateString()));
        $filterDate = $this->resolveNullableDate($request->query('filter_date'));
        $filterDay = $this->resolveNullableInt($request->query('filter_day'));
        $filterHour = $this->resolveNullableInt($request->query('filter_hour'));

        [$periodStart, $periodEnd] = $this->periodByView($focusDate, $view);

        $events = ExitPermit::query()
            ->whereDate('permit_date', '>=', $periodStart->toDateString())
            ->whereDate('permit_date', '<=', $periodEnd->toDateString())
            ->where('exit_type', ExitPermit::EXIT_TYPE_BUSINESS_TRIP)
            ->where('order_car', true)
            ->where('status', '!=', 'rejected')
            ->orderBy('permit_date')
            ->orderBy('start_time')
            ->get([
                'id',
                'permit_date',
                'start_time',
                'end_time',
                'destination',
                'exit_type',
                'vehicle_plate',
                'driver_name',
            ])
            ->map(function (ExitPermit $permit) {
                $date = Carbon::parse((string) $permit->permit_date);
                $isArranged = filled($permit->vehicle_plate) && filled($permit->driver_name);

                return [
                    'id' => $permit->id,
                    'permit_date' => $date->toDateString(),
                    'day_name' => $date->locale('id')->translatedFormat('l'),
                    'start_time' => $this->toHourMinute($permit->start_time),
                    'end_time' => $this->toHourMinute($permit->end_time),
                    'destination' => $permit->destination,
                    'exit_type' => $permit->exit_type,
                    'vehicle_plate' => $permit->vehicle_plate,
                    'driver_name' => $permit->driver_name,
                    'is_arranged' => $isArranged,
                ];
            })
            ->filter(function (array $event) use ($filterDate, $filterDay, $filterHour) {
                if ($filterDate && $event['permit_date'] !== $filterDate->toDateString()) {
                    return false;
                }

                if ($filterDay !== null) {
                    $dayIso = Carbon::parse($event['permit_date'])->dayOfWeekIso;
                    if ($dayIso !== $filterDay) {
                        return false;
                    }
                }

                if ($filterHour !== null && $event['start_time']) {
                    $hour = (int) substr((string) $event['start_time'], 0, 2);
                    if ($hour !== $filterHour) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();

        $arrangeItems = ExitPermit::query()
            ->where('exit_type', ExitPermit::EXIT_TYPE_BUSINESS_TRIP)
            ->where('order_car', true)
            ->where('status', 'pending')
            ->orderBy('permit_date')
            ->orderBy('start_time')
            ->get(['id', 'permit_date', 'start_time', 'end_time', 'destination', 'vehicle_plate', 'driver_name'])
            ->map(function (ExitPermit $permit) {
                $isArranged = filled($permit->vehicle_plate) && filled($permit->driver_name);

                return [
                    'id' => $permit->id,
                    'label' => sprintf(
                        '#%d | %s | %s-%s | %s',
                        $permit->id,
                        $this->toDateOnly($permit->permit_date),
                        $this->toHourMinute($permit->start_time) ?? '-',
                        $this->toHourMinute($permit->end_time) ?? '-',
                        $permit->destination,
                    ),
                    'is_arranged' => $isArranged,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('ScheduleCars/Index', [
            'events' => $events,
            'arrangeItems' => $arrangeItems,
            'filters' => [
                'view' => $view,
                'date' => $focusDate->toDateString(),
                'filter_date' => $filterDate?->toDateString(),
                'filter_day' => $filterDay,
                'filter_hour' => $filterHour,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorizeRatnaOnly();

        return Inertia::render('ScheduleCars/Create', [
            'targets' => $this->arrangeTargets(onlyUnarranged: true),
            'carOptions' => $this->carOptions(),
            'driverOptions' => $this->driverOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRatnaOnly();

        $validated = $request->validate([
            'exit_permit_id' => ['required', 'integer', 'exists:exit_permits,id'],
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $exitPermit = ExitPermit::query()->findOrFail((int) $validated['exit_permit_id']);
        $this->ensureCanArrange($exitPermit);

        $car = Car::query()->findOrFail((int) $validated['car_id']);
        $driver = Driver::query()->findOrFail((int) $validated['driver_id']);

        $exitPermit->vehicle_plate = strtoupper((string) $car->police_no);
        $exitPermit->driver_name = (string) $driver->name;
        $exitPermit->save();

        $this->logArrangement($exitPermit, $car, $driver, 'create');

        return redirect()->route('schedule-cars.edit', $exitPermit)->with('success', 'Arrange order car berhasil dibuat.');
    }

    public function edit(ExitPermit $exitPermit): Response
    {
        $this->authorizeRatnaOnly();
        $this->ensureCanArrange($exitPermit);

        $history = $exitPermit->scheduleCarArrangementLogs()
            ->with('arranger:id,name')
            ->get()
            ->map(fn(ScheduleCarArrangementLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'arranged_at' => optional($log->arranged_at)->toDateTimeString(),
                'arranger_name' => $log->arranger?->name,
                'vehicle_plate' => $log->vehicle_plate,
                'driver_name' => $log->driver_name,
            ])
            ->values()
            ->all();

        return Inertia::render('ScheduleCars/Edit', [
            'scheduleItem' => [
                'id' => $exitPermit->id,
                'permit_date' => $this->toDateOnly($exitPermit->permit_date),
                'start_time' => $this->toHourMinute($exitPermit->start_time),
                'end_time' => $this->toHourMinute($exitPermit->end_time),
                'destination' => $exitPermit->destination,
                'vehicle_plate' => $exitPermit->vehicle_plate,
                'driver_name' => $exitPermit->driver_name,
            ],
            'carOptions' => $this->carOptions(),
            'driverOptions' => $this->driverOptions(),
            'history' => $history,
        ]);
    }

    public function update(Request $request, ExitPermit $exitPermit): RedirectResponse
    {
        $this->authorizeRatnaOnly();
        $this->ensureCanArrange($exitPermit);

        $validated = $request->validate([
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $car = Car::query()->findOrFail((int) $validated['car_id']);
        $driver = Driver::query()->findOrFail((int) $validated['driver_id']);

        $exitPermit->vehicle_plate = strtoupper((string) $car->police_no);
        $exitPermit->driver_name = (string) $driver->name;
        $exitPermit->save();

        $this->logArrangement($exitPermit, $car, $driver, 'update');

        return redirect()->route('schedule-cars.edit', $exitPermit)->with('success', 'Arrange order car berhasil diperbarui.');
    }

    private function arrangeTargets(bool $onlyUnarranged = false): array
    {
        return ExitPermit::query()
            ->with([
                'user:id,name',
                'requestors:id,exit_permit_id,name,department,reimburs_lunch_box',
            ])
            ->where('exit_type', ExitPermit::EXIT_TYPE_BUSINESS_TRIP)
            ->where('order_car', true)
            ->where('status', 'pending')
            ->when($onlyUnarranged, fn($query) => $query->where(function ($nestedQuery) {
                $nestedQuery->whereNull('vehicle_plate')->orWhereNull('driver_name');
            }))
            ->orderBy('permit_date')
            ->orderBy('start_time')
            ->get([
                'id',
                'user_id',
                'permit_date',
                'start_time',
                'end_time',
                'destination',
                'reason',
                'notes',
            ])
            ->map(function (ExitPermit $permit) {
                $requestorNames = $permit->requestors
                    ->pluck('name')
                    ->filter()
                    ->values();

                $departmentLabels = $permit->requestors
                    ->pluck('department')
                    ->filter()
                    ->map(fn(string $department) => trim($department))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $permit->id,
                    'label' => sprintf(
                        '#%d | %s | %s-%s | %s',
                        $permit->id,
                        $this->toDateOnly($permit->permit_date),
                        $this->toHourMinute($permit->start_time) ?? '-',
                        $this->toHourMinute($permit->end_time) ?? '-',
                        $permit->destination,
                    ),
                    'template' => [
                        'tanggal_dinas_luar' => $this->toDateOnly($permit->permit_date) ?? '-',
                        'estimasi_jam' => sprintf(
                            '%s - %s',
                            $this->toHourMinuteDot($permit->start_time),
                            $this->toHourMinuteDot($permit->end_time),
                        ),
                        'nama_pt_tujuan' => $permit->destination ?: '-',
                        'lokasi_pt_tujuan' => $permit->destination ?: '-',
                        'user_yang_pergi' => $requestorNames->isNotEmpty()
                            ? $requestorNames->join(', ')
                            : ($permit->user?->name ?: '-'),
                        'budget_dept_cost_center' => $departmentLabels->isNotEmpty()
                            ? $departmentLabels->map(fn(string $department) => sprintf('%s (Cost Center: -)', $department))->join('; ')
                            : '-',
                        'alasan_pergi' => $permit->reason ?: '-',
                        'detail_barang_delivery' => $permit->notes ?: '-',
                        'permintaan_kurangi_catering' => $this->cateringReductionSummary($permit),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function toHourMinuteDot(?string $time): string
    {
        return str_replace(':', '.', $this->toHourMinute($time) ?? '--.--');
    }

    private function cateringReductionSummary(ExitPermit $permit): string
    {
        $requestorsAskReduction = $permit->requestors
            ->filter(fn($requestor) => strtoupper(trim((string) ($requestor->reimburs_lunch_box ?? 'N'))) === 'Y')
            ->pluck('name')
            ->filter()
            ->values();

        if ($requestorsAskReduction->isEmpty()) {
            return 'Tidak ada permintaan pengurangan catering.';
        }

        return sprintf(
            'Kurangi %d pax untuk: %s',
            $requestorsAskReduction->count(),
            $requestorsAskReduction->join(', '),
        );
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

    private function ensureCanArrange(ExitPermit $exitPermit): void
    {
        if (
            $exitPermit->exit_type !== ExitPermit::EXIT_TYPE_BUSINESS_TRIP
            || !(bool) $exitPermit->order_car
            || $exitPermit->status !== 'pending'
        ) {
            abort(403);
        }
    }

    private function logArrangement(ExitPermit $exitPermit, Car $car, Driver $driver, string $action): void
    {
        ScheduleCarArrangementLog::query()->create([
            'exit_permit_id' => $exitPermit->id,
            'arranged_by' => request()->user()->id,
            'arranged_at' => now(),
            'action' => $action,
            'car_id' => $car->id,
            'driver_id' => $driver->id,
            'vehicle_plate' => strtoupper((string) $car->police_no),
            'driver_name' => (string) $driver->name,
        ]);
    }

    private function authorizeRatnaOnly(): void
    {
        $email = strtolower((string) request()->user()?->email);

        if ($email !== self::RATNA_EMAIL) {
            abort(403);
        }
    }

    private function resolveDate(string $value): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable $exception) {
            return now()->startOfDay();
        }
    }

    private function resolveNullableDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function periodByView(Carbon $focusDate, string $view): array
    {
        if ($view === 'day') {
            return [$focusDate->copy(), $focusDate->copy()];
        }

        if ($view === 'month') {
            return [$focusDate->copy()->startOfMonth(), $focusDate->copy()->endOfMonth()];
        }

        return [$focusDate->copy()->startOfWeek(Carbon::MONDAY), $focusDate->copy()->endOfWeek(Carbon::SUNDAY)];
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
}
