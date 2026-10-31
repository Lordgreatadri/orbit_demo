<?php

namespace App\Tenancy;

use App\Models\Organization;
use App\Models\OrganizationDomain;

final class TenantResolution
{
    public const KIND_CENTRAL = 'central';

    public const KIND_TENANT = 'tenant';

    public const KIND_UNKNOWN = 'unknown';

    public const KIND_INACTIVE = 'inactive';

    public const KIND_UNVERIFIED = 'unverified';

    private function __construct(
        public readonly string $kind,
        public readonly string $host,
        public readonly ?OrganizationDomain $domain = null,
        public readonly ?Organization $organization = null,
    ) {}

    public static function central(string $host): self
    {
        return new self(self::KIND_CENTRAL, $host);
    }

    public static function tenant(string $host, OrganizationDomain $domain, Organization $organization): self
    {
        return new self(self::KIND_TENANT, $host, $domain, $organization);
    }

    public static function unknown(string $host): self
    {
        return new self(self::KIND_UNKNOWN, $host);
    }

    public static function inactive(string $host, OrganizationDomain $domain, ?Organization $organization = null): self
    {
        return new self(self::KIND_INACTIVE, $host, $domain, $organization);
    }

    public static function unverified(string $host, OrganizationDomain $domain): self
    {
        return new self(self::KIND_UNVERIFIED, $host, $domain);
    }

    public function isCentral(): bool
    {
        return $this->kind === self::KIND_CENTRAL;
    }

    public function isTenant(): bool
    {
        return $this->kind === self::KIND_TENANT;
    }
}
