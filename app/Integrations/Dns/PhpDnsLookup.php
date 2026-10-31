<?php

namespace App\Integrations\Dns;

use App\Contracts\DnsLookup;

class PhpDnsLookup implements DnsLookup
{
    public function txt(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            if (isset($record['txt']) && is_string($record['txt'])) {
                $values[] = trim($record['txt'], '"');
            }
        }

        return $values;
    }
}
