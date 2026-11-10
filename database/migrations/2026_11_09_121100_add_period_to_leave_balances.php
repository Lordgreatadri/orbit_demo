<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Period columns for a leave balance. An empty table receives the period
 * unique key here. A table that already has rows keeps the old year key
 * until orbit:data:backfill-leave-periods has given every row its own period.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leave_balances', 'period_start')) {
            return;
        }

        $empty = DB::table('leave_balances')->count() === 0;

        Schema::table('leave_balances', function (Blueprint $table) use ($empty): void {
            if ($empty) {
                $table->date('period_start')->default('2000-01-01')->after('year');
                $table->date('period_end')->default('2000-12-31')->after('period_start');
            } else {
                $table->date('period_start')->nullable()->after('year');
                $table->date('period_end')->nullable()->after('period_start');
            }

            $table->integer('pending_days')->default(0)->after('used_days');
            $table->integer('adjusted_days')->default(0)->after('pending_days');
        });

        if ($empty) {
            $this->usePeriodKey();
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leave_balances', 'period_start')) {
            return;
        }

        if (Schema::hasIndex('leave_balances', 'leave_balances_period_unique')) {
            Schema::table('leave_balances', function (Blueprint $table): void {
                $table->dropUnique('leave_balances_period_unique');
                $table->unique(['staff_member_id', 'leave_type_id', 'year']);
            });
        }

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->dropColumn(['period_start', 'period_end', 'pending_days', 'adjusted_days']);
        });
    }

    private function usePeriodKey(): void
    {
        if (Schema::hasIndex('leave_balances', 'leave_balances_period_unique')) {
            return;
        }

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->dropUnique(['staff_member_id', 'leave_type_id', 'year']);
            $table->unique(['staff_member_id', 'leave_type_id', 'period_start'], 'leave_balances_period_unique');
        });
    }
};
