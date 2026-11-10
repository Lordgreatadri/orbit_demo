<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the calendar-year balance key with the period key once every
 * existing row has a period. That fill is orbit:data:backfill-leave-periods.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('leave_balances', 'leave_balances_period_unique')) {
            return;
        }

        if (! Schema::hasColumn('leave_balances', 'period_start')) {
            return;
        }

        $unset = (int) DB::table('leave_balances')->whereNull('period_start')->count();

        if ($unset > 0) {
            throw new RuntimeException('Leave balances still have no period. Run php artisan orbit:data:backfill-leave-periods, then migrate again.');
        }

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->dropUnique(['staff_member_id', 'leave_type_id', 'year']);
            $table->unique(['staff_member_id', 'leave_type_id', 'period_start'], 'leave_balances_period_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('leave_balances', 'leave_balances_period_unique')) {
            return;
        }

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->dropUnique('leave_balances_period_unique');
            $table->unique(['staff_member_id', 'leave_type_id', 'year']);
        });
    }
};
