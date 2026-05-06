<?php

namespace App\Http\Controllers;

use App\Models\ExitPermit;
use Carbon\Carbon;
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

        return Inertia::render('ScheduleCars/Index', [
            'events' => $events,
            'filters' => [
                'view' => $view,
                'date' => $focusDate->toDateString(),
                'filter_date' => $filterDate?->toDateString(),
                'filter_day' => $filterDay,
                'filter_hour' => $filterHour,
            ],
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
}
