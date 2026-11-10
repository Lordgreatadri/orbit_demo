<?php

namespace App\Database\Upgrades;

/**
 * Counts for one data-upgrade run. Personal data is never included.
 */
final class UpgradeReport
{
    public function __construct(
        public int $processed = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $errors = 0,
    ) {}
}
