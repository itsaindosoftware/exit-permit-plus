<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ]);

        $rawNik = trim((string) $validated['nik']);
        $normalizedNik = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rawNik) ?? '');

        $user = \App\Models\User::query()
            ->with('role:id,code,name')
            ->where('nik', $rawNik)
            ->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(UPPER(nik), '.', ''), '-', ''), ' ', '') = ?",
                [$normalizedNik],
            )
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid NIK or password.',
            ], 422);
        }

        $deviceId = trim($validated['device_id']);
        $deviceHash = hash('sha256', $deviceId);

        if (!$user->login_device_hash) {
            $user->login_device_hash = $deviceHash;
            $user->save();
        }

        if (!hash_equals((string) $user->login_device_hash, $deviceHash)) {
            return response()->json([
                'message' => 'This account can only log in from the registered device.',
            ], 403);
        }

        $fcmToken = trim((string) ($validated['fcm_token'] ?? ''));

        if ($fcmToken !== '' && $fcmToken !== (string) $user->fcm_token) {
            $user->fcm_token = $fcmToken;
            $user->save();
        }

        // Keep one active token per account so relogin from the same device rotates token cleanly.
        $user->tokens()->delete();

        $token = $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'nik' => $user->nik,
                'email' => $user->email,
                'role' => $user->role?->code,
                'role_name' => $user->role?->name,
                'has_fcm_token' => filled($user->fcm_token),
            ],
        ]);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->fcm_token = trim((string) ($validated['fcm_token'] ?? '')) ?: null;
        $user->save();

        return response()->json([
            'message' => 'FCM token updated.',
            'has_fcm_token' => filled($user->fcm_token),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}
