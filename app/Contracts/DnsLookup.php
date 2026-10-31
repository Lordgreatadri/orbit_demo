<?php

namespace App\Contracts;

interface DnsLookup
{
    /**
     * TXT records for `$name`, as raw strings without surrounding quotes.
     *
     * @return list<string>
     */
    public function txt(string $name): array;
}
