<?php

namespace App\Http\Requests\Auth;

use App\Auth\WorkspaceLoginHandoff;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    private ?string $workspaceHandoffUrl = null;

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
     * Returns null when the password is correct for an organisation account
     * on the platform site. The browser is then sent to that workspace,
     * where the session is created. Call {@see workspaceHandoffUrl()}.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?User
    {
        $this->ensureIsNotRateLimited();

        $attempted = User::query()->where('email', $this->input('email'))->first();

        if ($attempted instanceof User && ! $this->accountBelongsOnThisHost($attempted)) {
            if (! Hash::check((string) $this->input('password'), (string) $attempted->password)) {
                $this->fail($attempted);
            }

            $handoff = $this->handoffUrlFor($attempted);
            if ($handoff !== null) {
                RateLimiter::clear($this->throttleKey());
                $this->workspaceHandoffUrl = $handoff;

                return null;
            }

            if (! $attempted->isActive() || ($attempted->organization && ! $attempted->organization->isOperational())) {
                $this->fail($attempted, __('This account is not currently able to sign in. Contact your organisation administrator.'));
            }

            $this->rejectForHost($attempted);
        }

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

        RateLimiter::clear($this->throttleKey());

        // Sign-in history (login_events) is written by RecordLoginEvent from the
        // framework's auth events; the audit trail is reserved for data changes.
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    public function workspaceHandoffUrl(): ?string
    {
        return $this->workspaceHandoffUrl;
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
    private function accountBelongsOnThisHost(User $user): bool
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return $user->isPlatformUser();
        }

        return ! $user->isPlatformUser() && (int) $user->organization_id === $tenantId;
    }

    /**
     * A correct password on the platform site continues on the organisation's
     * primary hostname. A correct password on someone else's workspace does not.
     */
    private function handoffUrlFor(User $user): ?string
    {
        if (app(TenantContext::class)->id() !== null || $user->isPlatformUser()) {
            return null;
        }

        $organization = $user->organization;
        $domain = $organization?->primaryDomain();

        if ($organization === null || $domain === null || ! $domain->is_primary || ! $domain->isResolvable()) {
            return null;
        }

        if (! $user->isActive() || ! $organization->isOperational()) {
            return null;
        }

        return app(WorkspaceLoginHandoff::class)->url($user, $domain->domain, $this->boolean('remember'));
    }

    /**
     * @throws ValidationException
     */
    private function fail(User $user, ?string $message = null): never
    {
        RateLimiter::hit($this->throttleKey());

        event(new Failed(Auth::getDefaultDriver(), $user, $this->only('email', 'password')));

        throw ValidationException::withMessages([
            'email' => $message ?? __('auth.failed'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function rejectForHost(User $user): never
    {
        $message = app(TenantContext::class)->id() === null && ! $user->isPlatformUser()
            ? __('Organisation members sign in at their workspace URL, not on the ORBIT platform site.')
            : __('auth.failed');

        $this->fail($user, $message);
    }
}
