<?php

namespace App\Actions\Tenancy;

use App\Enums\DomainType;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Tenancy\Hostname;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ensures an organisation has a primary platform subdomain, a branding row,
 * and a private storage directory. Idempotent — safe from factories and seeders.
 */
class ProvisionOrganizationWorkspace
{
    public function handle(Organization $organization, ?int $actorId = null): OrganizationDomain
    {
        $this->ensureStorage($organization);
        $this->ensureBranding($organization, $actorId);

        $existing = $organization->domains()->primary()->first();
        if ($existing) {
            return $existing;
        }

        $host = $this->allocatePrimaryHost($organization);

        return $organization->domains()->create([
            'domain' => $host,
            'type' => DomainType::PlatformSubdomain,
            'is_primary' => true,
            'is_active' => true,
            'is_verified' => true,
            'verified_at' => now(),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    public function ensureStorage(Organization $organization): void
    {
        $path = 'organizations/'.$organization->uuid.'/branding';
        Storage::disk('organizations')->makeDirectory($path);
    }

    public function ensureBranding(Organization $organization, ?int $actorId = null): void
    {
        $organization->branding()->firstOrCreate(
            ['organization_id' => $organization->getKey()],
            [
                'display_name' => $organization->name,
                'theme_mode' => 'user',
                'font_family' => 'instrument-sans',
                'support_email' => $organization->contact_email,
                'support_phone' => $organization->contact_phone,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );
    }

    private function allocatePrimaryHost(Organization $organization): string
    {
        $candidates = [];
        $slug = $organization->slug;

        if (Hostname::isValidPlatformLabel($slug)) {
            $candidates[] = $slug;
        }

        $fromName = Str::limit(Str::slug($organization->name), 40, '');
        if ($fromName !== '' && $fromName !== $slug && Hostname::isValidPlatformLabel($fromName)) {
            $candidates[] = $fromName;
        }

        foreach ($candidates as $label) {
            $host = Hostname::platformHostFor($label);
            if (! OrganizationDomain::query()->where('domain', $host)->exists()) {
                return $host;
            }
        }

        do {
            $label = Str::slug(Str::limit($organization->name, 24, '')).'-'.Str::lower(Str::random(4));
            $host = Hostname::platformHostFor($label);
        } while (! Hostname::isValidPlatformLabel($label) || OrganizationDomain::query()->where('domain', $host)->exists());

        return $host;
    }
}
