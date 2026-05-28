<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'nik' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'Employee ID is required.',
            'password.required' => 'Password is required.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $rawNik = trim((string) $this->input('nik'));
        $normalizedNik = $this->normalizeNik($rawNik);

        $user = User::query()
            ->where('nik', $rawNik)
            ->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(UPPER(nik), '.', ''), '-', ''), ' ', '') = ?",
                [$normalizedNik],
            )
            ->first();

        if (!$user || !Hash::check((string) $this->input('password'), (string) $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'nik' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'nik' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->normalizeNik((string) $this->string('nik'))) . '|' . $this->ip());
    }

    private function normalizeNik(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');
    }
}
