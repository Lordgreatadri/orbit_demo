<?php

namespace App\Tenancy;

use App\Enums\DomainType;
use App\Models\OrganizationDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Hosts Symfony/Laravel will accept. Patterns are regular expressions
 * (see Request::setTrustedHosts). Rejecting anything else blocks Host-header injection.
 */
class TrustHostList
{
    public const CUSTOM_DOMAINS_CACHE_KEY = 'organization.domains.trusted-custom';

    public static function forgetCustomDomains(): void
    {
        Cache::forget(self::CUSTOM_DOMAINS_CACHE_KEY);
    }

    /**
     * @return list<string|null>
     */
    public function hosts(): array
    {
        $base = Hostname::baseDomain();
        $hosts = [
            '^(.+\.)?'.preg_quote($base, '/').'$',
        ];

        foreach (Hostname::centralHosts() as $central) {
            $hosts[] = '^'.preg_quote($central, '/').'$';
        }

        foreach ($this->verifiedCustomDomains() as $domain) {
            $hosts[] = '^'.preg_quote($domain, '/').'$';
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * @return list<string>
     */
    private function verifiedCustomDomains(): array
    {
        try {
            if (! Schema::hasTable('organization_domains')) {
                return [];
            }

            return Cache::remember(self::CUSTOM_DOMAINS_CACHE_KEY, 300, function (): array {
                return OrganizationDomain::query()
                    ->where('type', DomainType::CustomDomain->value)
                    ->where('is_verified', true)
                    ->where('is_active', true)
                    ->pluck('domain')
                    ->all();
            });
        } catch (Throwable) {
            return [];
        }
    }
}
