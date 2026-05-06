<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load('role:id,code,name');
        $todayAttendance = $this->todayAttendance($user->id);

        return response()->json([
            'message' => 'Data dashboard berhasil diambil.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->code,
                    'role_name' => $user->role?->name,
                ],
                'attendance' => [
                    'date' => now()->toDateString(),
                    'check_in_at' => optional($todayAttendance?->check_in_at)?->toDateTimeString(),
                    'check_out_at' => optional($todayAttendance?->check_out_at)?->toDateTimeString(),
                    'check_in_location' => [
                        'latitude' => $todayAttendance?->check_in_latitude,
                        'longitude' => $todayAttendance?->check_in_longitude,
                        'street_area' => $todayAttendance?->check_in_street_area,
                        'village' => $todayAttendance?->check_in_village,
                        'district' => $todayAttendance?->check_in_district,
                        'regency' => $todayAttendance?->check_in_regency,
                    ],
                    'check_out_location' => [
                        'latitude' => $todayAttendance?->check_out_latitude,
                        'longitude' => $todayAttendance?->check_out_longitude,
                        'street_area' => $todayAttendance?->check_out_street_area,
                        'village' => $todayAttendance?->check_out_village,
                        'district' => $todayAttendance?->check_out_district,
                        'regency' => $todayAttendance?->check_out_regency,
                    ],
                ],
            ],
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'street_area' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'regency' => ['nullable', 'string', 'max:120'],
        ]);
        $attendance = $this->todayAttendance($user->id);

        if ($attendance?->check_in_at) {
            return response()->json([
                'message' => 'Absen masuk hari ini sudah tercatat.',
            ], 422);
        }

        if (!$attendance) {
            $attendance = new Attendance([
                'user_id' => $user->id,
                'attendance_date' => now()->toDateString(),
            ]);
        }

        $attendance->check_in_at = now();
        $attendance->check_in_ip = $request->ip();
        $attendance->check_in_latitude = $validated['latitude'] ?? null;
        $attendance->check_in_longitude = $validated['longitude'] ?? null;
        $attendance->check_in_street_area = $validated['street_area'] ?? null;
        $attendance->check_in_village = $validated['village'] ?? null;
        $attendance->check_in_district = $validated['district'] ?? null;
        $attendance->check_in_regency = $validated['regency'] ?? null;
        $attendance->save();

        return response()->json([
            'message' => 'Absen masuk berhasil.',
            'data' => [
                'attendance_date' => (string) $attendance->attendance_date,
                'check_in_at' => optional($attendance->check_in_at)->toDateTimeString(),
                'check_out_at' => optional($attendance->check_out_at)->toDateTimeString(),
                'check_in_location' => [
                    'latitude' => $attendance->check_in_latitude,
                    'longitude' => $attendance->check_in_longitude,
                    'street_area' => $attendance->check_in_street_area,
                    'village' => $attendance->check_in_village,
                    'district' => $attendance->check_in_district,
                    'regency' => $attendance->check_in_regency,
                ],
            ],
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'street_area' => ['nullable', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'regency' => ['nullable', 'string', 'max:120'],
        ]);
        $attendance = $this->todayAttendance($user->id);

        if (!$attendance?->check_in_at) {
            return response()->json([
                'message' => 'Absen pulang tidak bisa dilakukan sebelum absen masuk.',
            ], 422);
        }

        if ($attendance->check_out_at) {
            return response()->json([
                'message' => 'Absen pulang hari ini sudah tercatat.',
            ], 422);
        }

        $attendance->check_out_at = now();
        $attendance->check_out_ip = $request->ip();
        $attendance->check_out_latitude = $validated['latitude'] ?? null;
        $attendance->check_out_longitude = $validated['longitude'] ?? null;
        $attendance->check_out_street_area = $validated['street_area'] ?? null;
        $attendance->check_out_village = $validated['village'] ?? null;
        $attendance->check_out_district = $validated['district'] ?? null;
        $attendance->check_out_regency = $validated['regency'] ?? null;
        $attendance->save();

        return response()->json([
            'message' => 'Absen pulang berhasil.',
            'data' => [
                'attendance_date' => (string) $attendance->attendance_date,
                'check_in_at' => optional($attendance->check_in_at)->toDateTimeString(),
                'check_out_at' => optional($attendance->check_out_at)->toDateTimeString(),
                'check_out_location' => [
                    'latitude' => $attendance->check_out_latitude,
                    'longitude' => $attendance->check_out_longitude,
                    'street_area' => $attendance->check_out_street_area,
                    'village' => $attendance->check_out_village,
                    'district' => $attendance->check_out_district,
                    'regency' => $attendance->check_out_regency,
                ],
            ],
        ]);
    }

    private function todayAttendance(int $userId): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();
    }
}
