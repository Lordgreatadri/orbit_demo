<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Failures are reported generically to avoid account enumeration.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive() || ($user->organization && ! $user->organization->isOperational())) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('This account is not currently able to sign in. Contact your organisation administrator.'),
            ]);
        }

        $this->assertHostMatchesAccount($user);

        RateLimiter::clear($this->throttleKey());

        // Sign-in history (login_events) is written by RecordLoginEvent from the
        // framework's auth events; the audit trail is reserved for data changes.
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * Platform accounts sign in on the central host. Organisation accounts
     * sign in on a hostname registered to their organisation. Host-only
     * cookies mean a successful login cannot be reused on another host.
     */
    private function assertHostMatchesAccount(User $user): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            if ($user->isPlatformUser()) {
                return;
            }

            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('Organisation members sign in at their workspace URL, not on the ORBIT platform site.'),
            ]);
        }

        if ($user->isPlatformUser() || (int) $user->organization_id !== $tenantId) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
    }
}
