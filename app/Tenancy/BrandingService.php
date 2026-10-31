<?php

namespace App\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationBranding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class BrandingService
{
    private ?WorkspaceBranding $requestPreview = null;

    public function for(?Organization $organization, bool $preview = false): WorkspaceBranding
    {
        if ($this->requestPreview !== null) {
            return $this->requestPreview;
        }

        if ($organization === null) {
            return WorkspaceBranding::platform();
        }

        if ($preview) {
            $record = $organization->branding;

            return $record
                ? WorkspaceBranding::preview($organization, $record)
                : WorkspaceBranding::for($organization);
        }

        return Cache::remember($this->cacheKey($organization), 3600, function () use ($organization): WorkspaceBranding {
            $organization->loadMissing('branding');

            return WorkspaceBranding::for($organization, $organization->branding);
        });
    }

    public function sharePreview(WorkspaceBranding $branding): void
    {
        $this->requestPreview = $branding;
        View::share('workspaceBranding', $branding);
    }

    public function clearPreview(): void
    {
        $this->requestPreview = null;
    }

    public function forget(Organization $organization): void
    {
        Cache::forget($this->cacheKey($organization));
    }

    public function cacheKey(Organization $organization): string
    {
        return 'organization.'.$organization->getKey().'.branding';
    }

    public function record(Organization $organization): OrganizationBranding
    {
        return $organization->branding ?? $organization->branding()->firstOrCreate(
            ['organization_id' => $organization->getKey()],
            [
                'theme_mode' => 'user',
                'font_family' => 'instrument-sans',
                'display_name' => $organization->name,
                'support_email' => $organization->contact_email,
                'support_phone' => $organization->contact_phone,
            ],
        );
    }
}
