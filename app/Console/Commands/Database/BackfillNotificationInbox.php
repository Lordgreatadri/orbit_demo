<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\NotificationInboxBackfill;
use Illuminate\Console\Command;

class BackfillNotificationInbox extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-notification-inbox';

    protected $description = 'Fill a missing inbox organisation from its user and a missing event from its payload';

    public function handle(NotificationInboxBackfill $backfill): int
    {
        return $this->finish($backfill->run());
    }
}
