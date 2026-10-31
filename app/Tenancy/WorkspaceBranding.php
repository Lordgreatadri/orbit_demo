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
     * Preview uses unpublished draft values when present.
     */
    public static function preview(Organization $organization, OrganizationBranding $record): self
    {
        $draft = is_array($record->draft) ? $record->draft : [];
        $merged = $record->replicate();
        foreach (['display_name', 'primary_color', 'secondary_color', 'accent_color', 'theme_mode', 'font_family', 'login_heading', 'login_message', 'support_email', 'support_phone', 'footer_text'] as $field) {
            if (array_key_exists($field, $draft) && $draft[$field] !== null && $draft[$field] !== '') {
                $merged->{$field} = $draft[$field];
            }
        }

        return self::for($organization, $merged);
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

    public function withAssets(Organization $organization, ?OrganizationBranding $record): self
    {
        $url = function (?string $slot) use ($organization): ?string {
            if ($slot === null || $slot === '') {
                return null;
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
            logoUrl: $record?->logo_path ? $url('logo') : null,
            logoLightUrl: $record?->logo_light_path ? $url('logo-light') : null,
            logoDarkUrl: $record?->logo_dark_path ? $url('logo-dark') : null,
            faviconUrl: $record?->favicon_path ? $url('favicon') : null,
            loginImageUrl: $record?->login_image_path ? $url('login-image') : null,
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
}
