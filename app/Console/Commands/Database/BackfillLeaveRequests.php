<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\LeaveRequestCoverageBackfill;
use Illuminate\Console\Command;

class BackfillLeaveRequests extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-leave-requests';

    protected $description = 'Fill day counts for leave requests that do not have one yet';

    public function handle(LeaveRequestCoverageBackfill $backfill): int
    {
        return $this->finish($backfill->run());
    }
}
