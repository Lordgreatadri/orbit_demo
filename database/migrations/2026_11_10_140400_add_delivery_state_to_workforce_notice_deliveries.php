<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery state for a workforce notice. This is provider progress
 * (queued, sent, failed). It is not the user's read state.
 *
 * `status` is nullable and has no default. A row written before this column
 * existed stays null. Null is not queued: the sender treats it as a claim
 * that already finished, so those notices are not sent again while the
 * column is empty. `php artisan orbit:data:backfill-notice-claims` records
 * that null as `sent`. New claims set `queued` themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workforce_notice_deliveries')) {
            return;
        }

        if (! Schema::hasColumn('workforce_notice_deliveries', 'status')) {
            Schema::table('workforce_notice_deliveries', function (Blueprint $table): void {
                $table->string('status', 16)->nullable();
                $table->string('failure_kind', 32)->nullable();
                $table->unsignedTinyInteger('attempts')->default(1);
            });
        }

        if (! Schema::hasIndex('workforce_notice_deliveries', 'workforce_notice_status_index')) {
            Schema::table('workforce_notice_deliveries', function (Blueprint $table): void {
                $table->index(['organization_id', 'status'], 'workforce_notice_status_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workforce_notice_deliveries')) {
            return;
        }

        if (Schema::hasIndex('workforce_notice_deliveries', 'workforce_notice_status_index')) {
            Schema::table('workforce_notice_deliveries', function (Blueprint $table): void {
                $table->dropIndex('workforce_notice_status_index');
            });
        }

        if (Schema::hasColumn('workforce_notice_deliveries', 'status')) {
            Schema::table('workforce_notice_deliveries', function (Blueprint $table): void {
                $table->dropColumn(['status', 'failure_kind', 'attempts']);
            });
        }
    }
};
