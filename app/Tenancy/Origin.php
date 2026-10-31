<?php

namespace App\Tenancy;

use App\Models\Organization;
use Illuminate\Support\Facades\URL;

/**
 * Builds absolute origins for the central platform and a tenant workspace.
 * Scheme follows APP_URL so local http and production https stay consistent.
 */
final class Origin
{
    public static function scheme(): string
    {
        $appUrl = (string) config('app.url', 'http://orbit.test');

        return str_starts_with($appUrl, 'https:') ? 'https' : 'http';
    }

    public static function central(): string
    {
        return self::scheme().'://'.Hostname::centralHosts()[0];
    }

    public static function centralUrl(string $path = '/'): string
    {
        return self::central().'/'.ltrim($path, '/');
    }

    public static function of(Organization $organization): string
    {
        return self::scheme().'://'.$organization->primaryHost();
    }

    public static function url(Organization $organization, string $path = '/'): string
    {
        return self::of($organization).'/'.ltrim($path, '/');
    }

    public static function force(string $origin): void
    {
        URL::forceRootUrl($origin);
        URL::forceScheme(parse_url($origin, PHP_URL_SCHEME) ?: self::scheme());
    }

    public static function restore(): void
    {
        URL::forceRootUrl(config('app.url'));
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            URL::forceScheme($scheme);
        }
    }
}
