<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Hostname;
use App\Tenancy\Origin;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'type',
    'status',
    'timezone',
    'country_code',
    'contact_email',
    'contact_phone',
    'address_line',
    'city',
    'logo_path',
    'settings',
    'verified_at',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'status' => OrganizationStatus::class,
            'settings' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(OrganizationDomain::class);
    }

    public function branding(): HasOne
    {
        return $this->hasOne(OrganizationBranding::class);
    }

    public function primaryDomain(): ?OrganizationDomain
    {
        if ($this->relationLoaded('domains')) {
            return $this->domains->firstWhere('is_primary', true) ?? $this->domains->first();
        }

        return $this->domains()->primary()->first() ?? $this->domains()->first();
    }

    public function primaryHost(): string
    {
        return $this->primaryDomain()?->domain ?? Hostname::platformHostFor($this->slug);
    }

    public function workspaceUrl(string $path = '/'): string
    {
        return Origin::url($this, $path);
    }

    public function isEducational(): bool
    {
        return $this->type === OrganizationType::EducationalInstitution;
    }

    public function isDemo(): bool
    {
        return (bool) ($this->settings['demo'] ?? false);
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    public function markVerified(): void
    {
        if ($this->status === OrganizationStatus::PendingVerification) {
            $this->forceFill([
                'status' => OrganizationStatus::Active,
                'verified_at' => now(),
            ])->save();
        }
    }

    /**
     * Build a unique, URL-safe slug from a display name.
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), 150, '') ?: 'organisation';
        if (Hostname::isReservedSubdomain($base)) {
            $base .= '-org';
        }
        $slug = $base;

        while (static::query()->where('slug', $slug)->exists() || Hostname::isReservedSubdomain($slug)) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
