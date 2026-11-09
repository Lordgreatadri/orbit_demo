<?php

namespace App\Workforce;

use App\Enums\VerificationMethod;
use App\Models\AttendanceSession;
use App\Models\Location;

final class VerificationBundle
{
    /**
     * @param  list<VerificationDecision>  $decisions
     */
    public function __construct(
        public readonly array $decisions,
        public readonly ?AttendanceSession $session,
        public readonly ?Location $location,
    ) {}

    public function acceptedStandalone(): bool
    {
        foreach ($this->decisions as $decision) {
            if ($decision->accepted && $decision->method->isStandalone()) {
                return true;
            }
        }

        return false;
    }

    public function forMethod(VerificationMethod $method): ?VerificationDecision
    {
        foreach ($this->decisions as $decision) {
            if ($decision->method === $method) {
                return $decision;
            }
        }

        return null;
    }
}
