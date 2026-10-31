<?php

namespace App\Actions\Tenancy;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use App\Models\OrganizationBranding;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\BrandColor;
use App\Tenancy\BrandingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationBranding
{
    public const SLOTS = [
        'logo' => 'logo_path',
        'logo_light' => 'logo_light_path',
        'logo_dark' => 'logo_dark_path',
        'favicon' => 'favicon_path',
        'login_image' => 'login_image_path',
    ];

    public function __construct(private readonly BrandingService $branding) {}

    /**
     * Persist a draft. Call {@see PublishOrganizationBranding} to go live.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, UploadedFile|null>  $files
     */
    public function saveDraft(Organization $organization, array $attributes, array $files, User $actor): OrganizationBranding
    {
        $record = $this->branding->record($organization);
        $draft = is_array($record->draft) ? $record->draft : [];

        foreach (['display_name', 'login_heading', 'login_message', 'support_email', 'support_phone', 'footer_text'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $draft[$field] = $attributes[$field];
            }
        }

        foreach (['primary_color', 'secondary_color', 'accent_color'] as $field) {
            if (array_key_exists($field, $attributes) && filled($attributes[$field])) {
                $draft[$field] = BrandColor::normalize((string) $attributes[$field]);
            }
        }

        if (isset($attributes['theme_mode'])) {
            $draft['theme_mode'] = BrandThemeMode::from($attributes['theme_mode'])->value;
        }
        if (isset($attributes['font_family'])) {
            $draft['font_family'] = BrandFont::from($attributes['font_family'])->value;
        }

        foreach (self::SLOTS as $input => $column) {
            $file = $files[$input] ?? null;
            if ($file instanceof UploadedFile) {
                $draft[$column] = $this->storeFile($organization, $input, $file, $draft[$column] ?? null);
            }
        }

        $record->forceFill([
            'draft' => $draft,
            'updated_by' => $actor->getKey(),
        ])->save();

        Audit::log('organization.branding.drafted', $record, ['fields' => array_keys($draft)], $actor, $organization->getKey());

        return $record;
    }

    private function storeFile(Organization $organization, string $slot, UploadedFile $file, ?string $previous): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'ico'];

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                $slot => __('Upload a PNG, JPG, WEBP, or ICO file. SVG is not accepted.'),
            ]);
        }

        $directory = 'organizations/'.$organization->uuid.'/branding/draft';
        $name = str_replace('_', '-', $slot).'-'.Str::uuid().'.'.$extension;
        $path = $directory.'/'.$name;

        Storage::disk('organizations')->put($path, $file->getContent());

        if (is_string($previous) && $previous !== $path && str_contains($previous, '/branding/draft/')) {
            Storage::disk('organizations')->delete($previous);
        }

        return $path;
    }
}
