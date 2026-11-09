<?php

namespace App\Workforce;

use App\Enums\VerificationMethod;

final class VerificationDecision
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly VerificationMethod $method,
        public readonly bool $accepted,
        public readonly string $reason,
        public readonly array $evidence = [],
        public readonly ?int $tokenId = null,
        public readonly ?int $otpId = null,
    ) {}
}
