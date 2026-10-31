<?php

namespace App\Enums;

enum DomainType: string
{
    case PlatformSubdomain = 'platform_subdomain';
    case Alias = 'alias';
    case CustomDomain = 'custom_domain';

    public function label(): string
    {
        return match ($this) {
            self::PlatformSubdomain => 'Platform subdomain',
            self::Alias => 'Alias',
            self::CustomDomain => 'Custom domain',
        };
    }

    public function requiresVerification(): bool
    {
        return $this === self::CustomDomain;
    }
}
