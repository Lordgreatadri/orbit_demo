<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\UpgradeReport;

trait ReportsDataUpgrade
{
    protected function finish(UpgradeReport $report): int
    {
        $this->line('Processed: '.number_format($report->processed));
        $this->line('Updated: '.number_format($report->updated));
        $this->line('Skipped: '.number_format($report->skipped));
        $this->line('Errors: '.number_format($report->errors));

        return $report->errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
