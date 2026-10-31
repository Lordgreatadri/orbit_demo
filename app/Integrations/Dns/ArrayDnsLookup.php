<?php

namespace App\Integrations\Dns;

use App\Contracts\DnsLookup;

/**
 * Test double. Seed with `$lookup->put('_orbit-verify.example.test', ['token'])`.
 */
class ArrayDnsLookup implements DnsLookup
{
    /** @var array<string, list<string>> */
    private array $records = [];

    /**
     * @param  list<string>  $values
     */
    public function put(string $name, array $values): void
    {
        $this->records[strtolower($name)] = $values;
    }

    public function txt(string $name): array
    {
        return $this->records[strtolower($name)] ?? [];
    }
}
