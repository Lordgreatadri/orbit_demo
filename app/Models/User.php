<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'organization_id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'password',
    'status',
    'terms_accepted_at',
    'terms_version',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable;

    use HasRoles {
        assignRole as protected traitAssignRole;
        syncRoles as protected traitSyncRoles;
        givePermissionTo as protected traitGivePermissionTo;
        syncPermissions as protected traitSyncPermissions;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function staffMember(): HasOne
    {
        return $this->hasOne(StaffMember::class);
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    /**
     * Case-insensitive search across name and e-mail.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
        });
    }

    public function isPlatformSuperAdmin(): bool
    {
        return $this->isPlatformUser() && $this->hasRole('platform_super_admin');
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => mb_strtoupper(
            mb_substr((string) $this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1)
        ));
    }

    public function isPlatformUser(): bool
    {
        return $this->organization_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Both contact checks required for workspace access, unless a flag is off.
     */
    public function identityVerified(): bool
    {
        $emailReady = ! config('orbit.require_email_verification') || $this->hasVerifiedEmail();
        $phoneReady = ! config('orbit.require_phone_verification') || $this->hasVerifiedPhone();

        return $emailReady && $phoneReady;
    }

    public function belongsToOrganization(Organization|int $organization): bool
    {
        $id = $organization instanceof Organization ? $organization->getKey() : $organization;

        return $this->organization_id !== null && (int) $this->organization_id === (int) $id;
    }

    /**
     * Enforce the platform/tenant role boundary (ADR-0009) before delegating
     * to the permission package.
     */
    public function assignRole(...$roles): static
    {
        foreach ($this->flattenRoleNames($roles) as $name) {
            $this->assertRoleAllowed($name);
        }

        /** @var static */
        return $this->traitAssignRole(...$roles);
    }

    public function syncRoles(...$roles): static
    {
        $allowed = [];

        foreach ($this->flattenRoleNames($roles) as $name) {
            try {
                $this->assertRoleAllowed($name);
                $allowed[] = $name;
            } catch (InvalidArgumentException) {
                // Drop cross-boundary leftovers rather than leaving them attached.
            }
        }

        /** @var static */
        return $this->traitSyncRoles($allowed);
    }

    public function givePermissionTo(...$permissions): static
    {
        foreach ($this->flattenPermissionNames($permissions) as $name) {
            $this->assertPermissionAllowed($name);
        }

        /** @var static */
        return $this->traitGivePermissionTo(...$permissions);
    }

    public function syncPermissions(...$permissions): static
    {
        $allowed = [];

        foreach ($this->flattenPermissionNames($permissions) as $name) {
            try {
                $this->assertPermissionAllowed($name);
                $allowed[] = $name;
            } catch (InvalidArgumentException) {
                // Drop cross-boundary leftovers rather than leaving them attached.
            }
        }

        /** @var static */
        return $this->traitSyncPermissions($allowed);
    }

    protected function assertRoleAllowed(string $role): void
    {
        $record = Role::query()->where('name', $role)->first();

        if ($record === null) {
            throw new InvalidArgumentException("Unknown role [{$role}].");
        }

        if ($record->isPlatform() && ! $this->isPlatformUser()) {
            throw new InvalidArgumentException("Platform role [{$role}] cannot be assigned to an organisation account.");
        }

        if ($record->isTenant() && $this->isPlatformUser()) {
            throw new InvalidArgumentException("Tenant role [{$role}] cannot be assigned to a platform account.");
        }
    }

    protected function assertPermissionAllowed(string $permission): void
    {
        $record = Permission::query()->where('name', $permission)->first();

        if ($record === null) {
            throw new InvalidArgumentException("Unknown permission [{$permission}].");
        }

        if ($record->isPlatformOnly() && ! $this->isPlatformUser()) {
            throw new InvalidArgumentException("Platform permission [{$permission}] cannot be assigned to an organisation account.");
        }

        if (! $record->isPlatformOnly() && $this->isPlatformUser()) {
            throw new InvalidArgumentException("Tenant permission [{$permission}] cannot be assigned to a platform account.");
        }
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return array<int, string>
     */
    protected function flattenRoleNames(array $roles): array
    {
        $names = [];

        foreach (collect($roles)->flatten() as $role) {
            if ($role instanceof RoleContract) {
                $names[] = $role->name;
            } elseif (is_string($role) && $role !== '') {
                $names[] = $role;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return array<int, string>
     */
    protected function flattenPermissionNames(array $permissions): array
    {
        $names = [];

        foreach (collect($permissions)->flatten() as $permission) {
            if ($permission instanceof \Spatie\Permission\Contracts\Permission) {
                $names[] = $permission->name;
            } elseif (is_string($permission) && $permission !== '') {
                $names[] = $permission;
            }
        }

        return $names;
    }
}
