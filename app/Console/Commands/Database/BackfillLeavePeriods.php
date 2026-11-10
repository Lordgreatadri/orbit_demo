<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\LeavePeriodBackfill;
use Illuminate\Console\Command;

class BackfillLeavePeriods extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-leave-periods';

    protected $description = 'Fill leave balance periods that are still unset, without changing a period already chosen';

    public function handle(LeavePeriodBackfill $backfill): int
    {
        return $this->finish($backfill->run());
    }
}
