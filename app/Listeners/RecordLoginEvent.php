<?php

namespace App\Listeners;

use App\Enums\LoginEventType;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sign-in history, kept separately from the audit trail (requirements 06,
 * technical documentation §20 "Login history"). Never stores credentials.
 */
class RecordLoginEvent
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'onLogin']);
        $events->listen(Logout::class, [self::class, 'onLogout']);
        $events->listen(Failed::class, [self::class, 'onFailed']);
        $events->listen(Lockout::class, [self::class, 'onLockout']);
    }

    public function onLogin(Login $event): void
    {
        $this->record(LoginEventType::Login, $event->user instanceof User ? $event->user : null, null, $event->guard, $event->remember);
    }

    public function onLogout(Logout $event): void
    {
        $this->record(LoginEventType::Logout, $event->user instanceof User ? $event->user : null, null, $event->guard);
    }

    public function onFailed(Failed $event): void
    {
        $email = isset($event->credentials['email']) ? Str::lower((string) $event->credentials['email']) : null;

        $this->record(LoginEventType::Failed, $event->user instanceof User ? $event->user : null, $email, $event->guard);
    }

    public function onLockout(Lockout $event): void
    {
        $email = Str::lower((string) $event->request->input('email', ''));
        $user = $email !== '' ? User::query()->where('email', $email)->first() : null;

        $this->record(LoginEventType::Lockout, $user, $email !== '' ? $email : null);
    }

    private function record(LoginEventType $type, ?User $user, ?string $email, string $guard = 'web', bool $remember = false): void
    {
        /** @var Request $request */
        $request = app(Request::class);

        LoginEvent::query()->create([
            'user_id' => $user?->getKey(),
            'organization_id' => $user?->organization_id,
            'email' => $email ?? $user?->email,
            'event' => $type,
            'guard' => $guard,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'remember' => $remember,
        ]);
    }
}
