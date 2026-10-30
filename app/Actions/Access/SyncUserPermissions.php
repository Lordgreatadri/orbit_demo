<?php

namespace App\Actions\Access;

use App\Access\GrantResolver;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Direct (per-user) permissions layered on top of roles. Only permissions the
 * actor is allowed to grant are touched; anything else on the account is kept.
 */
class SyncUserPermissions
{
    public function __construct(private readonly GrantResolver $resolver) {}

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function handle(User $actor, User $target, array $permissionNames): User
    {
        if (! $this->resolver->canManage($actor, $target)) {
            throw ValidationException::withMessages(['permissions' => __('You cannot change this account\'s permissions.')]);
        }

        $grantable = $this->resolver->grantablePermissions($actor, $target)->pluck('name')->all();
        $requested = array_values(array_unique($permissionNames));

        foreach ($requested as $name) {
            if (! in_array($name, $grantable, true)) {
                throw ValidationException::withMessages(['permissions' => __('The permission ":permission" cannot be granted from this account.', ['permission' => $name])]);
            }
        }

        return DB::transaction(function () use ($actor, $target, $requested, $grantable): User {
            $before = $target->permissions()->pluck('name')->sort()->values()->all();
            $kept = array_values(array_diff($before, $grantable));
            $after = array_values(array_unique([...$kept, ...$requested]));

            $target->syncPermissions($after);
            $target->forgetCachedPermissions();

            sort($after);

            if ($before !== $after) {
                Audit::log('user.permissions_updated', $target, [
                    'before' => $before,
                    'after' => $after,
                ], $actor, $target->organization_id);
            }

            return $target->refresh();
        });
    }
}
