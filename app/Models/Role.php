<?php

namespace App\Models;

use App\Enums\RoleScope;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $name
 * @property RoleScope $scope
 * @property ?string $description
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    use HasPublicUuid;

    /** @var list<string> */
    protected $fillable = ['name', 'guard_name', 'scope', 'description', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_system' => 'boolean',
        ];
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('scope', RoleScope::Platform->value);
    }

    public function scopeTenant(Builder $query): Builder
    {
        return $query->where('scope', RoleScope::Tenant->value);
    }

    public function isPlatform(): bool
    {
        return $this->scope === RoleScope::Platform;
    }

    public function isTenant(): bool
    {
        return $this->scope === RoleScope::Tenant;
    }

    /**
     * Human label: config label for system roles, otherwise a tidy version of the name.
     */
    public function label(): string
    {
        return config('orbit.roles.'.$this->scope->value.'.'.$this->name)
            ?? ucfirst(str_replace(['_', '-'], ' ', $this->name));
    }
}
