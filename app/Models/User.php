<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        foreach ($this->flattenRoleNames($roles) as $name) {
            $this->assertRoleAllowed($name);
        }

        /** @var static */
        return $this->traitSyncRoles(...$roles);
    }

    protected function assertRoleAllowed(string $role): void
    {
        $platformRoles = array_keys(config('orbit.roles.platform', []));
        $tenantRoles = array_keys(config('orbit.roles.tenant', []));

        if (in_array($role, $platformRoles, true) && ! $this->isPlatformUser()) {
            throw new InvalidArgumentException("Platform role [{$role}] cannot be assigned to an organisation account.");
        }

        if (in_array($role, $tenantRoles, true) && $this->isPlatformUser()) {
            throw new InvalidArgumentException("Tenant role [{$role}] cannot be assigned to a platform account.");
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
            } elseif (is_string($role)) {
                $names[] = $role;
            }
        }

        return $names;
    }
}
