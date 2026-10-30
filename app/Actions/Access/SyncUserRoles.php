<?php

namespace App\Actions\Access;

use App\Access\GrantResolver;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncUserRoles
{
    public function __construct(private readonly GrantResolver $resolver) {}

    /**
     * @param  array<int, string>  $roleNames
     */
    public function handle(User $actor, User $target, array $roleNames): User
    {
        if (! $this->resolver->canManage($actor, $target)) {
            throw ValidationException::withMessages(['roles' => __('You cannot change this account\'s roles.')]);
        }

        $grantable = $this->resolver->grantableRoles($actor, $target)->pluck('name')->all();
        $requested = array_values(array_unique($roleNames));

        foreach ($requested as $name) {
            if (! in_array($name, $grantable, true)) {
                throw ValidationException::withMessages(['roles' => __('The role ":role" cannot be granted from this account.', ['role' => $name])]);
            }
        }

        return DB::transaction(function () use ($actor, $target, $requested, $grantable): User {
            $before = $target->roles()->pluck('name')->sort()->values()->all();

            // Roles outside the actor's reach are kept exactly as they are.
            $kept = array_values(array_diff($before, $grantable));
            $after = array_values(array_unique([...$kept, ...$requested]));

            $this->assertNotRemovingLastAdministrator($target, $before, $after);

            $target->syncRoles($after);
            $target->forgetCachedPermissions();

            sort($after);

            if ($before !== $after) {
                Audit::log('user.roles_updated', $target, [
                    'before' => $before,
                    'after' => $after,
                ], $actor, $target->organization_id);
            }

            return $target->refresh();
        });
    }

    /**
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    private function assertNotRemovingLastAdministrator(User $target, array $before, array $after): void
    {
        foreach (['organization_admin', 'platform_super_admin'] as $critical) {
            if (! in_array($critical, $before, true) || in_array($critical, $after, true)) {
                continue;
            }

            $others = User::query()
                ->whereKeyNot($target->getKey())
                ->where('status', 'active')
                ->when(
                    $critical === 'organization_admin',
                    fn ($q) => $q->where('organization_id', $target->organization_id),
                    fn ($q) => $q->whereNull('organization_id'),
                )
                ->role($critical)
                ->exists();

            if (! $others) {
                throw ValidationException::withMessages([
                    'roles' => __('This is the last active account with the ":role" role. Grant it to someone else first.', ['role' => config('orbit.roles.tenant.'.$critical, config('orbit.roles.platform.'.$critical, $critical))]),
                ]);
            }
        }
    }
}
