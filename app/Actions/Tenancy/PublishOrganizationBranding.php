<?php

namespace App\Actions\Tenancy;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\BrandingService;
use Illuminate\Support\Facades\Storage;

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
        foreach (['display_name', 'login_heading', 'login_message', 'support_email', 'support_phone', 'footer_text', 'primary_color', 'secondary_color', 'accent_color'] as $field) {
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

        foreach (UpdateOrganizationBranding::SLOTS as $column) {
            if (! array_key_exists($column, $draft)) {
                continue;
            }

            $live[$column] = $this->publishSlot($organization, $column, $draft[$column] ?? null, $record->{$column});
        }

        $record->forceFill($live + [
            'draft' => null,
            'published_at' => now(),
            'updated_by' => $actor->getKey(),
        ])->save();

        $this->deleteDraftDirectory($organization);
        $this->branding->forget($organization);

        Audit::log('organization.branding.published', $record, ['fields' => array_keys($live)], $actor, $organization->getKey());
    }

    private function publishSlot(Organization $organization, string $column, mixed $draftPath, ?string $currentPublished): ?string
    {
        $disk = Storage::disk('organizations');

        if (! is_string($draftPath) || $draftPath === '') {
            if (is_string($currentPublished) && $currentPublished !== '') {
                $disk->delete($currentPublished);
            }

            return null;
        }

        $slot = match ($column) {
            'logo_path' => 'logo',
            'logo_light_path' => 'logo-light',
            'logo_dark_path' => 'logo-dark',
            'favicon_path' => 'favicon',
            'login_image_path' => 'login-image',
            default => str_replace('_', '-', (string) preg_replace('/_path$/', '', $column)),
        };

        $extension = strtolower(pathinfo($draftPath, PATHINFO_EXTENSION) ?: 'bin');
        $published = 'organizations/'.$organization->uuid.'/branding/'.$slot.'.'.$extension;

        if ($disk->exists($draftPath) && $draftPath !== $published) {
            $disk->put($published, $disk->get($draftPath));
            $disk->delete($draftPath);
        }

        if ($currentPublished && $currentPublished !== $published && $currentPublished !== $draftPath) {
            $disk->delete($currentPublished);
        }

        return $disk->exists($published) ? $published : ($disk->exists($draftPath) ? $draftPath : null);
    }

    private function deleteDraftDirectory(Organization $organization): void
    {
        Storage::disk('organizations')->deleteDirectory('organizations/'.$organization->uuid.'/branding/draft');
    }
}
