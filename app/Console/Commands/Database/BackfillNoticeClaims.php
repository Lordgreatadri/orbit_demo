<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\NoticeClaimBackfill;
use Illuminate\Console\Command;

class BackfillNoticeClaims extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-notice-claims';

    protected $description = 'Record delivery claims that have no status as sent, without sending them again';

    public function handle(NoticeClaimBackfill $backfill): int
    {
        return $this->finish($backfill->run());
    }
}
