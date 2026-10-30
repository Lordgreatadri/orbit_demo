<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string $name
 * @property ?string $module
 * @property ?string $description
 * @property bool $is_system
 */
class Permission extends SpatiePermission
{
    use HasPublicUuid;

    /** @var list<string> */
    protected $fillable = ['name', 'guard_name', 'module', 'description', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Permissions that may be granted inside an organisation (everything that
     * is not platform-only).
     */
    public function scopeTenantGrantable(Builder $query): Builder
    {
        return $query->where('module', '!=', 'platform');
    }

    public function scopePlatformOnly(Builder $query): Builder
    {
        return $query->where('module', 'platform');
    }

    public function isPlatformOnly(): bool
    {
        return $this->module === 'platform';
    }

    public function label(): string
    {
        return $this->description ?: ucfirst(str_replace(['.', '_'], ' ', $this->name));
    }

    /**
     * Derive the module from the dotted name (e.g. "staff.manage" => "staff").
     */
    public static function moduleFromName(string $name): string
    {
        return str_contains($name, '.') ? strstr($name, '.', true) : 'general';
    }
}
