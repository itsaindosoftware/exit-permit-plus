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
            ],
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
