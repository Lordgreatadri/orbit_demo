<?php

namespace App\Tenancy;

/**
 * Normalises and classifies request hostnames. Never trusts an un-normalised
 * Host header: ports, trailing dots, and mixed case are stripped first.
 */
final class Hostname
{
    public const LABEL_MAX_LENGTH = 63;

    public static function normalize(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = rtrim($host, '.');

        if ($host === '' || str_contains($host, '/') || str_contains($host, ' ') || str_contains($host, '\\')) {
            return '';
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false && $host !== 'localhost' && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $host;
    }

    public static function baseDomain(): string
    {
        return (string) config('orbit.tenancy.base_domain', 'orbit.test');
    }

    /**
     * @return list<string>
     */
    public static function centralHosts(): array
    {
        /** @var list<string> $hosts */
        $hosts = config('orbit.tenancy.central_hosts', ['orbit.test']);

        return array_values(array_unique(array_map(fn (string $h) => self::normalize($h), $hosts)));
    }

    public static function isCentral(string $host): bool
    {
        return in_array($host, self::centralHosts(), true);
    }

    /**
     * Single-label subdomain of the platform base (epa.orbit.test → epa).
     * Nested labels (a.b.orbit.test) are not platform subdomains.
     */
    public static function platformSubdomain(string $host): ?string
    {
        $base = self::baseDomain();
        $suffix = '.'.$base;

        if ($host === $base || ! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        if ($label === '' || str_contains($label, '.')) {
            return null;
        }

        return $label;
    }

    public static function isReservedSubdomain(string $label): bool
    {
        return in_array(strtolower($label), config('orbit.tenancy.reserved_subdomains', []), true);
    }

    /**
     * A single DNS label that can be used as `{label}.{base_domain}`.
     */
    public static function isValidPlatformLabel(string $label): bool
    {
        if ($label === '' || strlen($label) > self::LABEL_MAX_LENGTH || str_contains($label, '.')) {
            return false;
        }

        if (self::isReservedSubdomain($label)) {
            return false;
        }

        $host = self::platformHostFor($label);

        return $host !== '' && self::normalize($host) === $host;
    }

    /**
     * @return list<string>
     */
    public static function reservedSubdomains(): array
    {
        return config('orbit.tenancy.reserved_subdomains', []);
    }

    public static function platformHostFor(string $subdomain): string
    {
        return strtolower($subdomain).'.'.self::baseDomain();
    }
}
