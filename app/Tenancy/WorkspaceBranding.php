<?php

namespace App\Tenancy;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use App\Models\OrganizationBranding;

/**
 * View/runtime DTO. Controllers and Blade receive this, never an Eloquent
 * branding query of their own.
 */
final class WorkspaceBranding
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $primary,
        public readonly string $secondary,
        public readonly string $accent,
        public readonly string $primaryHover,
        public readonly string $primarySoft,
        public readonly string $primaryFg,
        public readonly string $accentSoft,
        public readonly string $accentFg,
        public readonly BrandThemeMode $themeMode,
        public readonly BrandFont $font,
        public readonly string $loginHeading,
        public readonly string $loginMessage,
        public readonly string $supportEmail,
        public readonly ?string $supportPhone,
        public readonly string $footerText,
        public readonly ?string $logoUrl,
        public readonly ?string $logoLightUrl,
        public readonly ?string $logoDarkUrl,
        public readonly ?string $faviconUrl,
        public readonly ?string $loginImageUrl,
        public readonly bool $isDefault,
        public readonly ?Organization $organization,
    ) {}

    public static function platform(): self
    {
        $defaults = config('orbit.branding.defaults');

        return self::fromArray($defaults, organization: null, isDefault: true);
    }

    public static function for(Organization $organization, ?OrganizationBranding $record = null): self
    {
        $defaults = config('orbit.branding.defaults');
        $record ??= $organization->branding;

        $values = array_merge($defaults, array_filter([
            'display_name' => $record?->display_name ?: $organization->name,
            'primary_color' => $record?->primary_color,
            'secondary_color' => $record?->secondary_color,
            'accent_color' => $record?->accent_color,
            'theme_mode' => $record?->theme_mode?->value,
            'font_family' => $record?->font_family?->value,
            'login_heading' => $record?->login_heading,
            'login_message' => $record?->login_message,
            'support_email' => $record?->support_email ?: $organization->contact_email,
            'support_phone' => $record?->support_phone ?: $organization->contact_phone,
            'footer_text' => $record?->footer_text,
        ], fn ($v) => $v !== null && $v !== ''));

        $dto = self::fromArray($values, $organization, isDefault: $record === null || $record->published_at === null);

        return $dto->withAssets($organization, $record);
    }

    /**
     * Scalar snapshot. The cache store does not restore PHP objects.
     *
     * @return array<string, mixed>
     */
    public function toCacheArray(): array
    {
        return [
            'display_name' => $this->displayName,
            'primary' => $this->primary,
            'secondary' => $this->secondary,
            'accent' => $this->accent,
            'primary_hover' => $this->primaryHover,
            'primary_soft' => $this->primarySoft,
            'primary_fg' => $this->primaryFg,
            'accent_soft' => $this->accentSoft,
            'accent_fg' => $this->accentFg,
            'theme_mode' => $this->themeMode->value,
            'font' => $this->font->value,
            'login_heading' => $this->loginHeading,
            'login_message' => $this->loginMessage,
            'support_email' => $this->supportEmail,
            'support_phone' => $this->supportPhone,
            'footer_text' => $this->footerText,
            'is_default' => $this->isDefault,
            'assets' => [
                'logo' => $this->logoUrl !== null,
                'logo-light' => $this->logoLightUrl !== null,
                'logo-dark' => $this->logoDarkUrl !== null,
                'favicon' => $this->faviconUrl !== null,
                'login-image' => $this->loginImageUrl !== null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    public static function fromCache(array $cached, Organization $organization): ?self
    {
        foreach ([
            'display_name', 'primary', 'secondary', 'accent',
            'primary_hover', 'primary_soft', 'primary_fg',
            'accent_soft', 'accent_fg', 'theme_mode', 'font',
            'login_heading', 'login_message', 'support_email',
            'footer_text', 'is_default', 'assets',
        ] as $key) {
            if (! array_key_exists($key, $cached)) {
                return null;
            }
        }

        if (! is_array($cached['assets'])) {
            return null;
        }

        $theme = BrandThemeMode::tryFrom((string) $cached['theme_mode']);
        $font = BrandFont::tryFrom((string) $cached['font']);

        if ($theme === null || $font === null) {
            return null;
        }

        $dto = new self(
            displayName: (string) $cached['display_name'],
            primary: (string) $cached['primary'],
            secondary: (string) $cached['secondary'],
            accent: (string) $cached['accent'],
            primaryHover: (string) $cached['primary_hover'],
            primarySoft: (string) $cached['primary_soft'],
            primaryFg: (string) $cached['primary_fg'],
            accentSoft: (string) $cached['accent_soft'],
            accentFg: (string) $cached['accent_fg'],
            themeMode: $theme,
            font: $font,
            loginHeading: (string) $cached['login_heading'],
            loginMessage: (string) $cached['login_message'],
            supportEmail: (string) $cached['support_email'],
            supportPhone: isset($cached['support_phone']) ? (string) $cached['support_phone'] : null,
            footerText: (string) $cached['footer_text'],
            logoUrl: null,
            logoLightUrl: null,
            logoDarkUrl: null,
            faviconUrl: null,
            loginImageUrl: null,
            isDefault: (bool) $cached['is_default'],
            organization: $organization,
        );

        return $dto->withAssetSlots($organization, $cached['assets']);
    }

    /**
     * Preview uses unpublished draft values when present, including nulls
     * (cleared fields) and draft asset paths.
     */
    public static function preview(Organization $organization, OrganizationBranding $record): self
    {
        $draft = is_array($record->draft) ? $record->draft : [];
        $merged = $record->replicate();

        foreach ([
            'display_name', 'primary_color', 'secondary_color', 'accent_color',
            'theme_mode', 'font_family', 'login_heading', 'login_message',
            'support_email', 'support_phone', 'footer_text',
            'logo_path', 'logo_light_path', 'logo_dark_path', 'favicon_path', 'login_image_path',
        ] as $field) {
            if (array_key_exists($field, $draft)) {
                $merged->{$field} = $draft[$field];
            }
        }

        $dto = self::for($organization, $merged);

        return $dto->withAssets($organization, $merged, preview: true);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values, ?Organization $organization, bool $isDefault): self
    {
        $primary = BrandColor::try($values['primary_color'] ?? null, '#0F766E');
        $secondary = BrandColor::try($values['secondary_color'] ?? null, '#0D3B43');
        $accent = BrandColor::try($values['accent_color'] ?? null, '#3B6EA5');
        $theme = BrandThemeMode::tryFrom((string) ($values['theme_mode'] ?? 'user')) ?? BrandThemeMode::User;
        $font = BrandFont::tryFrom((string) ($values['font_family'] ?? 'instrument-sans')) ?? BrandFont::InstrumentSans;
        $name = (string) ($values['display_name'] ?? config('orbit.product_name'));

        return new self(
            displayName: $name,
            primary: $primary,
            secondary: $secondary,
            accent: $accent,
            primaryHover: BrandColor::hover($primary),
            primarySoft: BrandColor::soft($primary),
            primaryFg: BrandColor::ink($primary),
            accentSoft: BrandColor::soft($accent),
            accentFg: BrandColor::ink($accent),
            themeMode: $theme,
            font: $font,
            loginHeading: (string) ($values['login_heading'] ?? 'Sign in'),
            loginMessage: (string) ($values['login_message'] ?? 'Welcome back. Sign in to your organisation\'s workspace.'),
            supportEmail: (string) ($values['support_email'] ?? config('orbit.support_email')),
            supportPhone: $values['support_phone'] ?? null,
            footerText: (string) ($values['footer_text'] ?? ('© '.date('Y').' '.$name.'. Powered by '.config('orbit.product_name').'.')),
            logoUrl: null,
            logoLightUrl: null,
            logoDarkUrl: null,
            faviconUrl: null,
            loginImageUrl: null,
            isDefault: $isDefault,
            organization: $organization,
        );
    }

    public function withAssets(Organization $organization, ?OrganizationBranding $record, bool $preview = false): self
    {
        return $this->withAssetSlots($organization, [
            'logo' => filled($record?->logo_path),
            'logo-light' => filled($record?->logo_light_path),
            'logo-dark' => filled($record?->logo_dark_path),
            'favicon' => filled($record?->favicon_path),
            'login-image' => filled($record?->login_image_path),
        ], $preview);
    }

    /**
     * @param  array<string, mixed>  $present
     */
    private function withAssetSlots(Organization $organization, array $present, bool $preview = false): self
    {
        $url = function (string $slot) use ($organization, $present, $preview): ?string {
            if (empty($present[$slot])) {
                return null;
            }

            if ($preview) {
                return url('/branding/preview/'.$organization->uuid.'/'.$slot);
            }

            return Origin::url($organization, '/branding/'.$slot);
        };

        return new self(
            displayName: $this->displayName,
            primary: $this->primary,
            secondary: $this->secondary,
            accent: $this->accent,
            primaryHover: $this->primaryHover,
            primarySoft: $this->primarySoft,
            primaryFg: $this->primaryFg,
            accentSoft: $this->accentSoft,
            accentFg: $this->accentFg,
            themeMode: $this->themeMode,
            font: $this->font,
            loginHeading: $this->loginHeading,
            loginMessage: $this->loginMessage,
            supportEmail: $this->supportEmail,
            supportPhone: $this->supportPhone,
            footerText: $this->footerText,
            logoUrl: $url('logo'),
            logoLightUrl: $url('logo-light'),
            logoDarkUrl: $url('logo-dark'),
            faviconUrl: $url('favicon'),
            loginImageUrl: $url('login-image'),
            isDefault: $this->isDefault,
            organization: $organization,
        );
    }

    public function pageTitle(string $page): string
    {
        return $page.' · '.$this->displayName;
    }

    public function cssVariables(): string
    {
        return implode(';', [
            '--primary: '.$this->primary,
            '--primary-hover: '.$this->primaryHover,
            '--primary-soft: '.$this->primarySoft,
            '--primary-fg: '.$this->primaryFg,
            '--accent: '.$this->accent,
            '--accent-soft: '.$this->accentSoft,
            '--accent-fg: '.$this->accentFg,
            '--focus: '.$this->primary,
            '--font-sans: '.$this->font->cssStack(),
            '--brand-deep: '.$this->secondary,
        ]);
    }

    /**
     * Dark-theme washes. The light soft colours must not be copied onto plum,
     * or active navigation and tabs become light text on a near-white chip.
     */
    public function plumCssVariables(): string
    {
        return implode(';', [
            '--primary-soft: '.BrandColor::soft($this->primary, '#0B1D21', 0.78),
            '--accent-soft: '.BrandColor::soft($this->accent, '#0B1D21', 0.78),
        ]);
    }
}
