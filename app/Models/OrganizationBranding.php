<?php

namespace App\Models;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\OrganizationBrandingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'display_name',
    'logo_path',
    'logo_light_path',
    'logo_dark_path',
    'favicon_path',
    'login_image_path',
    'primary_color',
    'secondary_color',
    'accent_color',
    'theme_mode',
    'font_family',
    'login_heading',
    'login_message',
    'support_email',
    'support_phone',
    'footer_text',
    'draft',
    'published_at',
    'created_by',
    'updated_by',
])]
class OrganizationBranding extends Model
{
    /** @use HasFactory<OrganizationBrandingFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme_mode' => BrandThemeMode::class,
            'font_family' => BrandFont::class,
            'draft' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pathFor(string $slot): ?string
    {
        return match ($slot) {
            'logo' => $this->logo_path,
            'logo-light' => $this->logo_light_path,
            'logo-dark' => $this->logo_dark_path,
            'favicon' => $this->favicon_path,
            'login-image' => $this->login_image_path,
            default => null,
        };
    }
}
