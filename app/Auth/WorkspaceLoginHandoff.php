<?php

namespace App\Auth;

use App\Models\User;
use App\Tenancy\Hostname;
use App\Tenancy\Origin;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time transfer from the platform sign-in form to the organisation's
 * own hostname. The session cookie is host-only, so the password is checked
 * on the platform site and the session is created only on the workspace host.
 * The tenant still comes from that hostname, not from the token.
 */
final class WorkspaceLoginHandoff
{
    public function url(User $user, string $host, bool $remember): string
    {
        $token = Str::random(64);

        Cache::put($this->key($token), [
            'user_id' => $user->getKey(),
            'organization_id' => (int) $user->organization_id,
            'host' => Hostname::normalize($host),
            'remember' => $remember,
        ], now()->addSeconds(60));

        return Origin::forHost($host).'/login/handoff/'.$token;
    }

    /**
     * @return array{user: User, remember: bool}|null
     */
    public function pull(string $token, string $host, ?int $tenantId): ?array
    {
        $lock = Cache::lock($this->key($token).':lock', 5);

        try {
            $lock->block(3);
        } catch (LockTimeoutException) {
            return null;
        }

        try {
            $payload = Cache::get($this->key($token));

            if (! is_array($payload)) {
                return null;
            }

            if (Hostname::normalize($host) !== ($payload['host'] ?? null)) {
                return null;
            }

            if ($tenantId === null || (int) ($payload['organization_id'] ?? 0) !== $tenantId) {
                return null;
            }

            Cache::forget($this->key($token));

            $user = User::query()->find($payload['user_id'] ?? null);

            if (! $user instanceof User || $user->isPlatformUser() || (int) $user->organization_id !== $tenantId) {
                return null;
            }

            if (! $user->isActive() || ($user->organization && ! $user->organization->isOperational())) {
                return null;
            }

            return ['user' => $user, 'remember' => (bool) ($payload['remember'] ?? false)];
        } finally {
            $lock->release();
        }
    }

    private function key(string $token): string
    {
        return 'login-handoff:'.hash('sha256', $token);
    }
}
