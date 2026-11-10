<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\NotificationReadBackfill;
use Illuminate\Console\Command;

class BackfillNotificationReads extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-notification-reads';

    protected $description = 'Record one read audit for each inbox notice that was already read';

    public function handle(NotificationReadBackfill $backfill): int
    {
        return $this->finish($backfill->run());
    }
}
