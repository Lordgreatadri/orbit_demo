<?php

namespace App\Actions\Access;

use App\Access\GrantResolver;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUserStatus
{
    public function __construct(private readonly GrantResolver $resolver) {}

    public function handle(User $actor, User $target, UserStatus $status, ?string $reason = null): User
    {
        if (! $this->resolver->canManage($actor, $target)) {
            throw ValidationException::withMessages(['status' => __('You cannot change this account\'s status.')]);
        }

        if ($target->status === $status) {
            return $target;
        }

        if ($status === UserStatus::Suspended) {
            $this->assertNotLastActiveAdministrator($target);
        }

        return DB::transaction(function () use ($actor, $target, $status, $reason): User {
            $previous = $target->status;

            $target->forceFill(['status' => $status])->save();

            if ($status === UserStatus::Suspended) {
                $this->terminateSessions($target);
            }

            Audit::log('user.status_updated', $target, [
                'from' => $previous->value,
                'to' => $status->value,
                'reason' => $reason !== null ? mb_substr($reason, 0, 200) : null,
            ], $actor, $target->organization_id);

            return $target;
        });
    }

    private function assertNotLastActiveAdministrator(User $target): void
    {
        $critical = $target->isPlatformUser() ? 'platform_super_admin' : 'organization_admin';

        if (! $target->hasRole($critical)) {
            return;
        }

        $others = User::query()
            ->whereKeyNot($target->getKey())
            ->where('status', UserStatus::Active->value)
            ->when(
                $target->isPlatformUser(),
                fn ($q) => $q->whereNull('organization_id'),
                fn ($q) => $q->where('organization_id', $target->organization_id),
            )
            ->role($critical)
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                'status' => __('This is the last active administrator. Activate or promote another account before suspending it.'),
            ]);
        }
    }

    /**
     * Suspension should take effect immediately for the database session driver.
     * Other drivers rely on EnsureAccountIsOperational on the next request.
     */
    private function terminateSessions(User $target): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))->where('user_id', $target->getKey())->delete();
    }
}
