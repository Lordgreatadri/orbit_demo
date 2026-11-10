<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\ShiftClockBackfill;
use Illuminate\Console\Command;

class BackfillShiftClocks extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-shift-clocks';

    protected $description = 'Copy each shift clock onto assignments in that organisation that do not have one yet';

    public function handle(ShiftClockBackfill $backfill): int
    {
        return $this->finish($backfill->run());
    }
}
