<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Driver;
use App\Models\ExitPermit;
use App\Models\ScheduleCarArrangementLog;
use App\Notifications\ExitPermitCarArranged;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleCarController extends Controller
{
    private const RATNA_EMAIL = 'hrga-01@thaisummit.co.id';

    private const ARRANGE_EXIT_TYPES = [
        ExitPermit::EXIT_TYPE_BUSINESS_TRIP,
        ExitPermit::EXIT_TYPE_ASSIGNMENT,
        ExitPermit::EXIT_TYPE_COMPANY,
    ];

    private const ARRANGE_TEMPLATE_FIELDS = [
        'tanggal_dinas_luar',
        'estimasi_jam',
        'nama_pt_tujuan',
        'lokasi_pt_tujuan',
        'user_yang_pergi',
        'budget_dept_cost_center',
        'alasan_pergi',
        'detail_barang_delivery',
        'permintaan_kurangi_catering',
    ];

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
            ->whereIn('exit_type', self::ARRANGE_EXIT_TYPES)
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
                    'day_name' => $date->locale('en')->translatedFormat('l'),
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
            ->whereIn('exit_type', self::ARRANGE_EXIT_TYPES)
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
        $templateOverride = $this->validatedArrangeTemplate($request);

        $exitPermit = ExitPermit::query()->findOrFail((int) $validated['exit_permit_id']);
        $this->ensureCanArrange($exitPermit);

        $car = Car::query()->findOrFail((int) $validated['car_id']);
        $driver = Driver::query()->findOrFail((int) $validated['driver_id']);

        $exitPermit->vehicle_plate = strtoupper((string) $car->police_no);
        $exitPermit->driver_name = (string) $driver->name;
        $exitPermit->arrange_template_override = $templateOverride;
        $exitPermit->save();

        $this->notifyCarArrangementCompleted($exitPermit);

        $this->logArrangement($exitPermit, $car, $driver, 'create');

        return redirect()->route('schedule-cars.edit', $exitPermit)->with('success', 'Car arrangement created successfully.');
    }

    public function edit(ExitPermit $exitPermit): Response
    {
        $this->authorizeRatnaOnly();
        $this->ensureCanArrange($exitPermit);
        $exitPermit->loadMissing([
            'user:id,name',
            'requestors:id,exit_permit_id,name,department,reimburs_lunch_box',
            'costCenter:id,name,cost_center_sap,desc_cost_c',
        ]);

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
                'cost_center_name' => $exitPermit->costCenter?->name,
                'vehicle_plate' => $exitPermit->vehicle_plate,
                'driver_name' => $exitPermit->driver_name,
                'template' => $this->buildArrangeTemplate($exitPermit),
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

        $wasArranged = filled($exitPermit->vehicle_plate) && filled($exitPermit->driver_name);

        $validated = $request->validate([
            'car_id' => ['required', 'integer', 'exists:cars,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);
        $templateOverride = $this->validatedArrangeTemplate($request);

        $car = Car::query()->findOrFail((int) $validated['car_id']);
        $driver = Driver::query()->findOrFail((int) $validated['driver_id']);

        $exitPermit->vehicle_plate = strtoupper((string) $car->police_no);
        $exitPermit->driver_name = (string) $driver->name;
        $exitPermit->arrange_template_override = $templateOverride;
        $exitPermit->save();

        $isArranged = filled($exitPermit->vehicle_plate) && filled($exitPermit->driver_name);
        if (!$wasArranged && $isArranged) {
            $this->notifyCarArrangementCompleted($exitPermit);
        }

        $this->logArrangement($exitPermit, $car, $driver, 'update');

        return redirect()->route('schedule-cars.edit', $exitPermit)->with('success', 'Car arrangement updated successfully.');
    }

    private function arrangeTargets(bool $onlyUnarranged = false): array
    {
        return ExitPermit::query()
            ->with([
                'user:id,name',
                'requestors:id,exit_permit_id,name,department,reimburs_lunch_box',
                'costCenter:id,name,cost_center_sap,desc_cost_c',
            ])
            ->whereIn('exit_type', self::ARRANGE_EXIT_TYPES)
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
                    'template' => $this->buildArrangeTemplate($permit),
                ];
            })
            ->values()
            ->all();
    }

    private function buildArrangeTemplate(ExitPermit $permit): array
    {
        $costCenter = $permit->costCenter;
        $costCenterSap = trim((string) ($costCenter?->cost_center_sap ?? ''));
        $costCenterName = trim((string) ($costCenter?->name ?? ''));
        $costCenterDesc = trim((string) ($costCenter?->desc_cost_c ?? ''));
        $costCenterLabel = sprintf(
            '%s | %s | %s',
            $costCenterSap !== '' ? $costCenterSap : '-',
            $costCenterName !== '' ? $costCenterName : '-',
            $costCenterDesc !== '' ? $costCenterDesc : '-',
        );
        $requestorNames = $permit->requestors
            ->pluck('name')
            ->filter()
            ->values();

        $autoTemplate = [
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
            'budget_dept_cost_center' => $costCenterLabel,
            'alasan_pergi' => $permit->reason ?: '-',
            'detail_barang_delivery' => $permit->notes ?: '-',
            'permintaan_kurangi_catering' => $this->cateringReductionSummary($permit),
        ];

        $override = $this->normalizedArrangeTemplateOverride($permit->arrange_template_override);

        foreach (self::ARRANGE_TEMPLATE_FIELDS as $field) {
            if ($field === 'budget_dept_cost_center') {
                continue;
            }

            if (filled($override[$field] ?? null)) {
                $autoTemplate[$field] = $override[$field];
            }
        }

        return $autoTemplate;
    }

    private function validatedArrangeTemplate(Request $request): array
    {
        $validated = $request->validate([
            'arrange_template.tanggal_dinas_luar' => ['nullable', 'string', 'max:120'],
            'arrange_template.estimasi_jam' => ['nullable', 'string', 'max:120'],
            'arrange_template.nama_pt_tujuan' => ['nullable', 'string', 'max:255'],
            'arrange_template.lokasi_pt_tujuan' => ['nullable', 'string', 'max:255'],
            'arrange_template.user_yang_pergi' => ['nullable', 'string', 'max:1000'],
            'arrange_template.budget_dept_cost_center' => ['nullable', 'string', 'max:1000'],
            'arrange_template.alasan_pergi' => ['nullable', 'string', 'max:2000'],
            'arrange_template.detail_barang_delivery' => ['nullable', 'string', 'max:2000'],
            'arrange_template.permintaan_kurangi_catering' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->normalizedArrangeTemplateOverride($validated['arrange_template'] ?? []);
    }

    private function normalizedArrangeTemplateOverride(mixed $value): array
    {
        $source = is_array($value) ? $value : [];
        $normalized = [];

        foreach (self::ARRANGE_TEMPLATE_FIELDS as $field) {
            $normalized[$field] = isset($source[$field])
                ? trim((string) $source[$field])
                : '';
        }

        return $normalized;
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
            return 'No request to reduce catering.';
        }

        return sprintf(
            'Reduce %d pax for: %s',
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
            !in_array($exitPermit->exit_type, self::ARRANGE_EXIT_TYPES, true)
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
        $user = request()->user();
        $email = strtolower((string) $user?->email);

        if ($user?->role?->code === 'admin') {
            return;
        }

        if ($email !== self::RATNA_EMAIL) {
            abort(403);
        }
    }

    private function notifyCarArrangementCompleted(ExitPermit $exitPermit): void
    {
        $exitPermit->loadMissing('user:id,name,email');
        $owner = $exitPermit->user;

        if (!$owner || !filled($owner->email)) {
            return;
        }

        $owner->notify(new ExitPermitCarArranged($exitPermit));
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
