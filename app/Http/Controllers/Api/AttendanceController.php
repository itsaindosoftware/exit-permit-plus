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
                ],
            ],
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
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
        $attendance->save();

        return response()->json([
            'message' => 'Absen masuk berhasil.',
            'data' => [
                'attendance_date' => (string) $attendance->attendance_date,
                'check_in_at' => optional($attendance->check_in_at)->toDateTimeString(),
                'check_out_at' => optional($attendance->check_out_at)->toDateTimeString(),
            ],
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
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
        $attendance->save();

        return response()->json([
            'message' => 'Absen pulang berhasil.',
            'data' => [
                'attendance_date' => (string) $attendance->attendance_date,
                'check_in_at' => optional($attendance->check_in_at)->toDateTimeString(),
                'check_out_at' => optional($attendance->check_out_at)->toDateTimeString(),
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
