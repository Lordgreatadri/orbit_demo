<?php

namespace App\Actions\Tenancy;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\BrandingService;

class PublishOrganizationBranding
{
    public function __construct(private readonly BrandingService $branding) {}

    public function handle(Organization $organization, User $actor): void
    {
        $record = $this->branding->record($organization);
        $draft = is_array($record->draft) ? $record->draft : [];

        if ($draft === []) {
            $record->forceFill([
                'published_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->branding->forget($organization);

            return;
        }

        $live = [];
        foreach (['display_name', 'login_heading', 'login_message', 'support_email', 'support_phone', 'footer_text', 'primary_color', 'secondary_color', 'accent_color', 'logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_image_path'] as $field) {
            if (array_key_exists($field, $draft)) {
                $live[$field] = $draft[$field];
            }
        }
        if (isset($draft['theme_mode'])) {
            $live['theme_mode'] = BrandThemeMode::from($draft['theme_mode']);
        }
        if (isset($draft['font_family'])) {
            $live['font_family'] = BrandFont::from($draft['font_family']);
        }

        $record->forceFill($live + [
            'draft' => null,
            'published_at' => now(),
            'updated_by' => $actor->getKey(),
        ])->save();

        $this->branding->forget($organization);

        Audit::log('organization.branding.published', $record, ['fields' => array_keys($live)], $actor, $organization->getKey());
    }
}
